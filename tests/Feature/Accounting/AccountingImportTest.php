<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingDocument;
use App\Models\AccountingRule;
use App\Models\AccountingSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingImportTest extends TestCase
{
    use RefreshDatabase;

    private AccountingSupplier $supplier;

    private AccountingAccount $beer;

    private AccountingAccount $wine;

    private string $token = 'accounting-test-import-token-at-least-32-characters';

    protected function migrateFreshUsing(): array
    {
        // Unrelated HACCP migrations use MySQL-only SHOW INDEX on SQLite.
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/0001_01_01_000003_create_employees_table.php',
            'database/migrations/0001_01_01_000004_create_departments_table.php',
            'database/migrations/2026_10_20_130000_create_accounting_review_tables.php',
            'database/migrations/2026_10_21_100000_create_infosystem_tables.php',
            'database/migrations/2026_10_21_110000_add_workspace_access.php',
            'database/migrations/2026_10_27_130000_unique_accounting_source_reference.php',
        ]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['accounting.import_token' => $this->token]);
        $this->supplier = AccountingSupplier::create(['name' => 'Getränke Gato GmbH', 'creditor_account' => '70007', 'active' => true]);
        $this->beer = AccountingAccount::create(['account_number' => '5132', 'name' => 'Bier', 'active' => true]);
        $this->wine = AccountingAccount::create(['account_number' => '5133', 'name' => 'Wein', 'active' => true]);
    }

    private function payload(array $changes = []): array
    {
        return array_replace_recursive([
            'source' => 'n8n', 'source_reference' => 'mail-1:attachment-0',
            'supplier_name' => $this->supplier->name,
            'invoice_number' => 'G-100', 'invoice_date' => '2026-10-27',
            'net_cents' => 10000, 'tax_cents' => 1900, 'gross_cents' => 11900,
            'lines' => [[
                'position_no' => 1, 'article_number' => '00123', 'description' => 'Altbier',
                'net_cents' => 10000, 'tax_rate' => 19, 'tax_cents' => 1900, 'gross_cents' => 11900,
                'account_number' => '5133', 'confidence' => 99, 'classification_source' => 'ai',
            ]],
        ], $changes);
    }

    private function upload(array $changes = [], bool $pdf = true)
    {
        $body = ['payload' => json_encode($this->payload($changes))];
        if ($pdf) {
            $body['file'] = UploadedFile::fake()->createWithContent('gato.pdf', "%PDF-1.4\n%test invoice\n%%EOF");
        }

        return $this->withToken($this->token)->post('/api/integrations/accounting/documents', $body, ['Accept' => 'application/json']);
    }

    private function rule(array $changes = []): AccountingRule
    {
        return AccountingRule::create(array_replace([
            'supplier_id' => $this->supplier->id, 'match_type' => 'article_number', 'match_value' => '00123',
            'account_id' => $this->beer->id, 'priority' => 999, 'learned_from_manual' => true, 'active' => true,
            'tax_rate' => 7,
        ], $changes));
    }

    private function login(): void
    {
        $this->actingAs(User::factory()->create(['access_role' => 'admin']));
    }

    public function test_learned_article_beats_ai_description_and_supplier_default(): void
    {
        $this->rule();
        $this->rule(['match_type' => 'description_exact', 'match_value' => 'Altbier', 'account_id' => $this->wine->id, 'priority' => 1]);
        $this->rule(['match_type' => 'supplier_default', 'match_value' => '*', 'account_id' => $this->wine->id, 'priority' => 0, 'learned_from_manual' => false]);
        $this->upload()->assertCreated()
            ->assertJsonPath('document.lines.0.account_number_snapshot', '5132')
            ->assertJsonPath('document.lines.0.classification_source', 'learned_rule')
            ->assertJsonPath('document.lines.0.confidence', 100)
            ->assertJsonPath('document.lines.0.rule_key', 'article_number:00123')
            ->assertJsonPath('document.status', 'ready');
        $line = AccountingDocument::first()->lines()->first();
        $this->assertSame(19.0, (float) $line->tax_rate);
    }

    public function test_rules_never_fill_missing_tax_from_history(): void
    {
        $this->rule();
        $id = $this->upload(['lines' => [['tax_rate' => null]]])->assertCreated()->assertJsonPath('document.status', 'review')->json('document.id');
        $this->assertNull(AccountingDocument::find($id)->lines()->first()->tax_rate);
        $this->login();
        $this->postJson("/api/accounting/documents/$id/approve")->assertUnprocessable();
    }

    public function test_disabled_and_other_supplier_rules_are_ignored(): void
    {
        $this->rule(['active' => false]);
        $other = AccountingSupplier::create(['name' => 'Anderer Lieferant', 'creditor_account' => '70012']);
        $this->rule(['supplier_id' => $other->id]);
        $this->upload(['lines' => [['description' => 'Unbekannter Artikel']]])->assertCreated()->assertJsonPath('document.lines.0.account_number_snapshot', '5133')
            ->assertJsonPath('document.lines.0.classification_source', 'ai');
    }

    public function test_imported_errors_block_readiness_and_approval_even_after_account_correction(): void
    {
        $id = $this->upload(['validation_errors' => ['Unlesbare Steuerkennzeichnung']])->assertCreated()
            ->assertJsonPath('document.status', 'review')->json('document.id');
        $line = AccountingDocument::find($id)->lines()->first();
        $this->login();
        $this->patchJson("/api/accounting/documents/$id/lines/$line->id", ['account_id' => $this->beer->id])
            ->assertOk()->assertJsonPath('document_status', 'review');
        $this->postJson("/api/accounting/documents/$id/approve")->assertUnprocessable();
        $this->getJson("/api/accounting/documents/$id")->assertOk()
            ->assertJsonPath('blocking_errors.0', 'Unlesbare Steuerkennzeichnung');
    }

    public function test_missing_pdf_and_missing_invoice_fields_block_approval(): void
    {
        $id = $this->upload(['invoice_number' => null, 'invoice_date' => null], false)->assertCreated()
            ->assertJsonPath('document.status', 'review')->json('document.id');
        $this->login();
        $this->postJson("/api/accounting/documents/$id/approve")->assertUnprocessable();
    }

    public function test_net_and_tax_mismatch_block_even_when_gross_matches(): void
    {
        $id = $this->upload(['net_cents' => 9990, 'tax_cents' => 1910])->assertCreated()
            ->assertJsonPath('document.status', 'review')->json('document.id');
        $this->login();
        $this->postJson("/api/accounting/documents/$id/approve")->assertUnprocessable();
    }

    public function test_manual_corrections_and_original_survive_retry(): void
    {
        $id = $this->upload()->assertCreated()->json('document.id');
        $document = AccountingDocument::find($id);
        $line = $document->lines()->first();
        $path = $document->file_path;
        $this->login();
        $this->patchJson("/api/accounting/documents/$id/lines/$line->id", ['account_id' => $this->beer->id, 'remember_rule' => true])->assertOk();
        $this->upload(['gross_cents' => 1])->assertOk()->assertJsonPath('duplicate', true)
            ->assertJsonPath('document.lines.0.id', $line->id)
            ->assertJsonPath('document.lines.0.account_number_snapshot', '5132')
            ->assertJsonPath('document.file_path', $path)->assertJsonPath('document.gross_cents', 11900);
        $this->assertDatabaseCount('accounting_documents', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertNull(AccountingRule::first()->tax_rate);
    }

    public function test_approved_and_exported_documents_are_immutable(): void
    {
        $id = $this->upload()->assertCreated()->json('document.id');
        $this->login();
        $this->postJson("/api/accounting/documents/$id/approve")->assertOk()->assertJsonPath('document.status', 'approved');
        $line = AccountingDocument::find($id)->lines()->first();
        foreach (['approved', 'exported'] as $status) {
            AccountingDocument::find($id)->update(['status' => $status]);
            $this->upload()->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('document.status', $status);
            $this->patchJson("/api/accounting/documents/$id/lines/$line->id", ['account_id' => $this->beer->id])->assertStatus(409);
            $this->postJson("/api/accounting/documents/$id/approve")->assertStatus(409);
        }
    }

    public function test_unknown_supplier_cannot_create_global_learned_rule(): void
    {
        $id = $this->upload(['supplier_name' => 'Unbekannt'])->assertCreated()->json('document.id');
        $line = AccountingDocument::find($id)->lines()->first();
        $this->login();
        $this->patchJson("/api/accounting/documents/$id/lines/$line->id", ['account_id' => $this->beer->id, 'remember_rule' => true])->assertUnprocessable();
        $this->assertDatabaseCount('accounting_rules', 0);
    }

    public function test_missing_reference_and_invalid_token_are_rejected(): void
    {
        $this->upload(['source_reference' => null])->assertUnprocessable();
        $this->withToken('wrong')->postJson('/api/integrations/accounting/documents', $this->payload())->assertUnauthorized();
    }

    public function test_source_reference_unique_index_rejects_duplicate_rows(): void
    {
        $this->upload()->assertCreated();
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('accounting_documents')->insert(['source' => 'n8n', 'source_reference' => 'mail-1:attachment-0']);
    }

    public function test_migration_refuses_existing_duplicates_without_removing_documents(): void
    {
        $migration = require database_path('migrations/2026_10_27_130000_unique_accounting_source_reference.php');
        $migration->down();
        DB::table('accounting_documents')->insert([
            ['source' => 'n8n', 'source_reference' => 'existing-duplicate'],
            ['source' => 'n8n', 'source_reference' => 'existing-duplicate'],
        ]);
        try {
            $migration->up();
            $this->fail('Migration must refuse existing duplicates.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Doppelte Buchhaltungs-Quellreferenzen', $exception->getMessage());
        }
        $this->assertDatabaseCount('accounting_documents', 2);
    }

    public function test_inactive_account_blocks_approval_and_cannot_be_selected(): void
    {
        $id = $this->upload(['lines' => [['description' => 'Unbekannter Artikel']]])->assertCreated()->json('document.id');
        $line = AccountingDocument::find($id)->lines()->first();
        $this->wine->update(['active' => false]);
        $this->login();
        $this->postJson("/api/accounting/documents/$id/approve")->assertUnprocessable();
        $this->patchJson("/api/accounting/documents/$id/lines/$line->id", ['account_id' => $this->wine->id])->assertUnprocessable();
    }
}
