<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingDocument;
use App\Models\AccountingRule;
use App\Models\AccountingSupplier;
use App\Models\User;
use App\Services\Accounting\IsbInvoiceParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class IsbImportTest extends \Tests\TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
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
        config(['accounting.import_token' => 'accounting-test-import-token-at-least-32-characters']);
        $this->seed([\Database\Seeders\AccountingSupplierSeeder::class, \Database\Seeders\AccountingAccountSeeder::class]);
        AccountingAccount::create(['account_number' => '1599', 'name' => 'Test Verrechnung Rechnungsimport', 'kind' => 'interim', 'active' => true]);
        config(['accounting.lexware_clearing_account' => '1599']);
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/accounting/isb-layout.txt'));
    }

    private function upload(string $reference = 'isb-test')
    {
        return $this->withToken('accounting-test-import-token-at-least-32-characters')->post('/api/integrations/accounting/documents', [
            'payload' => json_encode(['source_reference' => $reference, 'extraction_profile' => 'isb_layout_v1',
                'supplier_name' => 'ignored', 'gross_cents' => 1]),
            'file' => UploadedFile::fake()->createWithContent('isb.pdf', "%PDF-1.4\n%ISB-test\n%%EOF"),
        ], ['Accept' => 'application/json']);
    }

    public function test_native_invoice_keeps_articles_dates_wrapped_names_and_exact_vat(): void
    {
        $data = (new IsbInvoiceParser)->parse($this->fixture());
        $this->assertSame('055001', $data['invoice_number']);
        $this->assertSame('2026-03-17', $data['invoice_date']);
        $this->assertSame(49818, $data['net_cents']);
        $this->assertSame(3487, $data['tax_cents']);
        $this->assertSame(53305, $data['gross_cents']);
        $this->assertCount(4, $data['lines']);
        $this->assertStringContainsString('Gew.', $data['lines'][0]['description']);
        $this->assertStringContainsString('Holland-Vitello', $data['lines'][2]['description']);
        $this->assertSame(['004179', '004243', '004430', '004501'], array_column($data['lines'], 'article_number'));
        $this->assertSame(3487, array_sum(array_column($data['lines'], 'tax_cents')));
    }

    public function test_import_approval_and_export_have_two_groups_and_one_full_creditor(): void
    {
        Process::fake(fn () => Process::result(output: $this->fixture()));
        $id = $this->upload()->assertCreated()->assertJsonPath('document.status', 'ready')->json('document.id');
        $doc = AccountingDocument::find($id);
        $this->assertSame('70018', $doc->supplier->creditor_account);
        $this->assertSame('isb_layout_v1', $doc->raw_payload['isb']['parser']);
        $this->assertSame("%PDF-1.4\n%ISB-test\n%%EOF", Storage::get($doc->file_path));
        foreach (['004179' => '5112', '004243' => '5112', '004430' => '5111', '004501' => '5111'] as $article => $account) {
            $this->assertSame($account, $doc->lines()->where('article_number', $article)->first()->account_number_snapshot);
        }
        $this->actingAs(User::factory()->create());
        $detail = $this->getJson('/api/accounting/documents/'.$id)->assertOk()->assertJsonPath('blocking_errors', [])
            ->assertJsonPath('booking_summary.unresolved_lines', 0)->assertJsonCount(2, 'booking_summary.rows');
        $groups = collect($detail->json('booking_summary.rows'))->keyBy('account_number');
        $this->assertSame(24889, $groups['5112']['net_cents']);
        $this->assertSame(1742, $groups['5112']['tax_cents']);
        $this->assertSame(24929, $groups['5111']['net_cents']);
        $this->assertSame(1745, $groups['5111']['tax_cents']);
        $this->postJson('/api/accounting/documents/'.$id.'/approve')->assertOk();
        $preview = $this->postJson('/api/accounting/exports/preview', ['year' => 2026, 'document_ids' => [$id]])
            ->assertOk()->assertJsonPath('row_count', 3)->assertJsonPath('total_cents', 53305)->json();
        $creditor = collect($preview['rows'])->where('role', 'creditor');
        $this->assertCount(1, $creditor);
        $this->assertSame('055001', $creditor->first()['text']);
        $this->assertSame('70018', $creditor->first()['credit_account']);
        $this->assertSame(53305, $creditor->first()['amount_cents']);
        $result = $this->postJson('/api/accounting/exports', ['year' => 2026, 'document_ids' => [$id], 'fingerprint' => $preview['fingerprint']])->assertCreated();
        $this->get($result->json('download_url'))->assertOk();
        $this->upload()->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('accounting_documents', 1);
    }

    public function test_learned_rule_precedes_supplier_rule_without_replacing_current_tax(): void
    {
        AccountingRule::create(['supplier_id' => AccountingSupplier::where('creditor_account', '70018')->value('id'),
            'match_type' => 'article_number', 'match_value' => '004179', 'learned_from_manual' => true,
            'account_id' => AccountingAccount::where('account_number', '5111')->value('id'), 'tax_rate' => 19, 'active' => true]);
        Process::fake(fn () => Process::result(output: $this->fixture()));
        $id = $this->upload()->assertCreated()->json('document.id');
        $line = AccountingDocument::find($id)->lines()->where('article_number', '004179')->first();
        $this->assertSame('5111', $line->account_number_snapshot);
        $this->assertSame('7.00', $line->tax_rate);
        $this->assertSame('learned_rule', $line->classification_source);
    }

    public function test_unknown_article_and_inactive_accounts_remain_reviewable(): void
    {
        $text = str_replace(['004179', 'Garnelen Sea Tiger'], ['999999', 'Unbekanntes Produkt'], $this->fixture());
        Process::fake(fn () => Process::result(output: $text));
        AccountingAccount::where('account_number', '5111')->update(['active' => false]);
        $id = $this->upload()->assertCreated()->assertJsonPath('document.status', 'review')->json('document.id');
        $doc = AccountingDocument::find($id);
        $this->assertNull($doc->lines()->where('article_number', '999999')->first()->account_id);
        $this->assertNull($doc->lines()->where('article_number', '004430')->first()->account_id);
        $resolver = new \App\Services\Accounting\IsbAccount;
        $this->assertNull($resolver->resolve(['description' => 'Hähnchen mit Garnelen']));
        $this->assertSame('5112', $resolver->resolve(['description' => 'Lachsfilet'])[0]);
        $this->assertSame('5111', $resolver->resolve(['description' => 'Hähnchenbrustfilet'])[0]);
    }

    public function test_failures_preserve_original_without_ai_fallback_and_missing_supplier_blocks(): void
    {
        Process::fake(fn () => Process::result(output: 'unreadable'));
        $id = $this->upload()->assertCreated()->assertJsonPath('document.status', 'analysis_failed')
            ->assertJsonPath('document.gross_cents', null)->json('document.id');
        $this->assertNotEmpty(AccountingDocument::find($id)->file_path);
        AccountingSupplier::where('creditor_account', '70018')->update(['active' => false]);
        $this->upload('inactive')->assertUnprocessable();
    }

    public function test_supplier_detection_needs_original_identifiers_and_manual_tax_changes_are_blocked(): void
    {
        Process::fake(fn () => Process::result(output: $this->fixture()));
        $this->assertSame('isb', app(\App\Services\Accounting\InvoiceSupplierDetector::class)->detect('/tmp/test.pdf')['supplier']);
        $id = $this->upload()->assertCreated()->json('document.id');
        $doc = AccountingDocument::find($id);
        $doc->lines()->where('article_number', '004179')->update(['tax_rate' => 19]);
        $this->assertContains('ISB-Steuergruppe 7 % stimmt nicht mit dem Original überein.', app(\App\Services\Accounting\DocumentValidation::class)->errors($doc));
        Process::fake(fn () => Process::result(output: 'ISB Seafood GmbH without VAT identity'));
        $this->assertNull(app(\App\Services\Accounting\InvoiceSupplierDetector::class)->detect('/tmp/test.pdf')['supplier']);
    }

    public function test_incomplete_rows_missing_tax_and_wrong_totals_are_rejected(): void
    {
        $text = $this->fixture();
        foreach ([
            str_replace('533,05', '533,06', $text), str_replace('498,18', '498,19', $text),
            str_replace('34,87', '34,88', $text), str_replace('7%', '', $text),
            str_replace('108,99', '118,99', $text), str_replace('6,430', '7,430', $text),
            str_replace('DE236175659', 'DE999999999', $text), str_replace('17.03.2026', '31.02.2026', $text),
            preg_replace('/^.*004243.*$/m', '', $text), $text."\fsecond page",
        ] as $bad) {
            try {
                (new IsbInvoiceParser)->parse($bad);
                $this->fail('Invalid invoice passed.');
            } catch (\RuntimeException $e) {
                $this->assertStringStartsWith('ISB-Analyse:', $e->getMessage());
            }
        }
    }
}
