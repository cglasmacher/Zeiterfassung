<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingDeleteTest extends TestCase
{
    use DatabaseMigrations;

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
    }

    private function document(array $changes = []): AccountingDocument
    {
        $document = AccountingDocument::create(array_replace([
            'source' => 'n8n', 'source_reference' => 'test-delete', 'status' => 'review',
            'invoice_number' => 'TEST-DELETE', 'file_path' => 'accounting-documents/test.pdf',
        ], $changes));
        $document->lines()->create(['description' => 'Testposition']);
        Storage::disk('local')->put($document->file_path, '%PDF-1.4 test');

        return $document;
    }

    public function test_delete_removes_document_positions_and_pdf_and_releases_import_reference(): void
    {
        $document = $this->document();
        $this->actingAs(User::factory()->create());
        $this->deleteJson('/api/accounting/documents/'.$document->id)->assertNoContent();
        $this->assertDatabaseMissing('accounting_documents', ['id' => $document->id]);
        $this->assertDatabaseCount('accounting_document_lines', 0);
        Storage::disk('local')->assertMissing($document->file_path);
        $replacement = $this->document();
        $this->assertNotSame($document->id, $replacement->id);
    }

    public function test_approved_exported_and_historically_approved_documents_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([['status' => 'approved'], ['status' => 'exported'], ['approved_at' => now()], ['exported_at' => now()]] as $i => $changes) {
            $document = $this->document([...$changes, 'source_reference' => 'locked-'.$i]);
            $this->deleteJson('/api/accounting/documents/'.$document->id)->assertStatus(409);
            $this->assertDatabaseHas('accounting_documents', ['id' => $document->id]);
            Storage::disk('local')->assertExists($document->file_path);
        }
    }

    public function test_delete_requires_admin_and_returns_not_found_for_unknown_document(): void
    {
        $document = $this->document();
        $this->deleteJson('/api/accounting/documents/'.$document->id)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['access_role' => 'operations']));
        $this->deleteJson('/api/accounting/documents/'.$document->id)->assertForbidden();
        $this->assertDatabaseHas('accounting_documents', ['id' => $document->id]);
        $this->actingAs(User::factory()->create());
        $this->deleteJson('/api/accounting/documents/999999')->assertNotFound();
    }

    public function test_original_shared_by_another_document_is_preserved(): void
    {
        $first = $this->document();
        $second = $this->document(['source_reference' => 'second']);
        $this->actingAs(User::factory()->create());
        $this->deleteJson('/api/accounting/documents/'.$first->id)->assertNoContent();
        Storage::disk('local')->assertExists($second->file_path);
        $this->deleteJson('/api/accounting/documents/'.$second->id)->assertNoContent();
        Storage::disk('local')->assertMissing($second->file_path);
    }
}
