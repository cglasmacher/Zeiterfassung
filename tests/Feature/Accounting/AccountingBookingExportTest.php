<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingDocument;
use App\Models\AccountingExportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingBookingExportTest extends TestCase
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
        config(['accounting.import_token' => str_repeat('x', 40)]);
        $this->seed([\Database\Seeders\AccountingSupplierSeeder::class, \Database\Seeders\AccountingAccountSeeder::class]);
        // Test-only account: production must configure its own dedicated account explicitly.
        AccountingAccount::create(['account_number' => '1599', 'name' => 'Test Verrechnung Rechnungsimport', 'kind' => 'interim', 'active' => true]);
        config(['accounting.lexware_clearing_account' => '1599']);
        Process::fake(fn () => Process::result(output: file_get_contents(base_path('tests/Fixtures/accounting/gato-layout.txt'))));
        $this->actingAs(User::factory()->create());
    }

    private function approved(): int
    {
        $id = $this->withToken(str_repeat('x', 40))->post('/api/integrations/accounting/documents', [
            'payload' => json_encode(['supplier_name' => 'Getränke Gato GmbH', 'extraction_profile' => 'gato_layout_v1', 'source_reference' => 'export-test']),
            'file' => UploadedFile::fake()->createWithContent('gato.pdf', "%PDF-1.4\n%test\n%%EOF"),
        ], ['Accept' => 'application/json'])->assertCreated()->json('document.id');
        $this->postJson('/api/accounting/documents/'.$id.'/approve')->assertOk();

        return $id;
    }

    private function preview(): array
    {
        return $this->postJson('/api/accounting/exports/preview', ['year' => 2026])->assertOk()->json();
    }

    private function create(array $preview)
    {
        return $this->postJson('/api/accounting/exports', ['year' => 2026,
            'document_ids' => array_column($preview['documents'], 'id'), 'fingerprint' => $preview['fingerprint']]);
    }

    private function assertSingleCreditor(array $rows, AccountingDocument $document): void
    {
        $rows = collect($rows)->where('document_id', $document->id)->values();
        $creditor = $document->supplier->creditor_account;
        $headers = $rows->filter(fn ($row) => in_array($creditor, [$row['debit_account'], $row['credit_account']], true));
        $this->assertCount(1, $headers);
        $this->assertSame($headers->first(), $rows->first());
        $this->assertSame('creditor', $rows->first()['role']);
        $this->assertSame($document->invoice_number, $rows->first()['text']);
        $this->assertSame(abs($document->gross_cents), $rows->first()['amount_cents']);
        $this->assertSame('', $rows->first()['tax_key']);
        $this->assertSame($creditor, $rows->first()[$document->gross_cents > 0 ? 'credit_account' : 'debit_account']);
        $clearing = (string) config('accounting.lexware_clearing_account');
        $this->assertSame(0, $rows->sum(fn ($row) => ($row['debit_account'] === $clearing ? $row['amount_cents'] : 0)
            - ($row['credit_account'] === $clearing ? $row['amount_cents'] : 0)));
        $this->assertTrue($rows->skip(1)->every(fn ($row) => $row['role'] === 'allocation' && str_starts_with($row['text'], $document->supplier->name.' · ')));
    }

    public function test_export_has_grouped_rows_explicit_deposit_and_reusable_immutable_download(): void
    {
        $id = $this->approved();
        $preview = $this->preview();
        $this->assertCount(1, $preview['documents']);
        $this->assertSame(7, $preview['row_count']);
        $this->assertSame(32481, $preview['total_cents']);
        $this->assertDatabaseCount('accounting_export_batches', 0);
        $this->assertSame('approved', AccountingDocument::find($id)->status);
        $this->assertSingleCreditor($preview['rows'], AccountingDocument::find($id));
        $rows = collect($preview['rows'])->where('role', 'allocation')->keyBy(fn ($row) => $row['credit_account'] === '1599' ? $row['debit_account'] : $row['credit_account']);
        $this->assertSame(238, $rows['1141']['amount_cents']);
        $this->assertSame('1599', $rows['1141']['debit_account']);
        $this->assertSame('', $rows['1141']['tax_key']);
        $this->assertSame(45, $rows['1461']['amount_cents']);
        $this->assertSame('', $rows['1461']['tax_key']);
        $this->assertSame('8', $rows['5110']['tax_key']);
        $this->assertSame('9', $rows['5132']['tax_key']);
        $result = $this->create($preview)->assertCreated();
        $download = $this->get($result->json('download_url'))->assertOk();
        $csv = $download->getContent();
        $decoded = iconv('Windows-1252', 'UTF-8', $csv);
        $this->assertStringContainsString('Steuerschlüssel', $decoded);
        $this->assertStringContainsString('Getränke Gato', $decoded);
        $this->assertStringContainsString('2,38;1599;1141;', $decoded);
        $this->assertStringContainsString('0,45;1599;1461;', $decoded);
        $records = array_map(fn ($line) => str_getcsv($line, ';', '"', ''), explode("\r\n", trim($decoded)));
        $this->assertSame(AccountingDocument::find($id)->invoice_number, $records[1][3]);
        $this->assertSame(['324,81', '1599', '70007', ''], array_slice($records[1], 4));
        $this->assertCount(1, array_filter(array_slice($records, 1), fn ($row) => in_array('70007', [$row[5], $row[6]], true)));
        $this->assertStringContainsString("\r\n", $csv);
        $this->assertFalse(str_starts_with($csv, "\xef\xbb\xbf"));
        $this->assertSame('exported', AccountingDocument::find($id)->status);
        AccountingAccount::where('account_number', '5132')->update(['name' => 'Spätere Änderung']);
        $this->assertSame($csv, $this->get($result->json('download_url'))->getContent());
        $this->create($preview)->assertUnprocessable();
        $this->assertDatabaseCount('accounting_export_batches', 1);
        $this->assertSame('lexware_ascii_ansi_single_creditor_v2', AccountingExportBatch::first()->metadata['format']);
        $this->getJson('/api/accounting/exports')->assertOk()->assertJsonCount(1, 'batches');
    }

    public function test_unapproved_or_wrong_year_documents_are_not_exported(): void
    {
        $id = $this->approved();
        $this->postJson('/api/accounting/exports/preview', ['year' => 2025])->assertUnprocessable();
        AccountingDocument::find($id)->update(['status' => 'review']);
        $this->postJson('/api/accounting/exports/preview', ['year' => 2026])->assertUnprocessable();
        $this->assertDatabaseCount('accounting_export_batches', 0);
    }

    public function test_changed_preview_or_deactivated_account_prevents_export(): void
    {
        $id = $this->approved();
        $preview = $this->preview();
        $bad = $preview;
        $bad['fingerprint'] = str_repeat('0', 64);
        $this->create($bad)->assertStatus(409);
        $this->assertSame('approved', AccountingDocument::find($id)->status);
        AccountingAccount::where('account_number', '1461')->update(['active' => false]);
        $this->create($preview)->assertUnprocessable();
        $this->assertDatabaseCount('accounting_export_batches', 0);
    }

    public function test_rounding_mismatch_cannot_silently_change_invoice_tax(): void
    {
        $id = $this->approved();
        $document = AccountingDocument::find($id);
        $document->lines()->where('article_number', '11388')->increment('tax_cents');
        $document->lines()->where('article_number', '11388')->increment('gross_cents');
        $document->increment('tax_cents');
        $document->increment('gross_cents');
        $document->update(['raw_payload' => null]);
        $this->postJson('/api/accounting/exports/preview', ['year' => 2026])->assertUnprocessable();
        $this->assertDatabaseCount('accounting_export_batches', 0);
    }

    public function test_export_requires_admin_even_for_download(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/accounting/exports')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['access_role' => 'operations']));
        $this->postJson('/api/accounting/exports/preview', ['year' => 2026])->assertForbidden();
        $this->get('/api/accounting/exports/1/download')->assertForbidden();
    }

    public function test_damaged_snapshot_is_not_downloaded(): void
    {
        $this->approved();
        $result = $this->create($this->preview())->assertCreated();
        $batch = AccountingExportBatch::first();
        $data = $batch->metadata;
        $data['csv_base64'] = base64_encode('changed');
        $batch->update(['metadata' => $data]);
        $this->getJson($result->json('download_url'))->assertStatus(409);
    }

    public function test_credit_note_reverses_accounts_and_keeps_csv_amounts_positive(): void
    {
        $id = $this->approved();
        $document = AccountingDocument::find($id);
        foreach ($document->lines as $line) {
            $line->update(['net_cents' => -$line->net_cents, 'tax_cents' => -$line->tax_cents, 'gross_cents' => -$line->gross_cents]);
        }
        $document->update(['net_cents' => -$document->net_cents, 'tax_cents' => -$document->tax_cents, 'gross_cents' => -$document->gross_cents, 'raw_payload' => null]);
        $preview = $this->preview();
        $this->assertSame(-32481, $preview['total_cents']);
        $rows = collect($preview['rows']);
        $this->assertSingleCreditor($preview['rows'], $document);
        $this->assertTrue($rows->every(fn ($row) => $row['amount_cents'] > 0));
        $this->assertSame('1599', $rows->firstWhere('debit_account', '1141')['credit_account']);
        $this->assertSame(45, $rows->firstWhere('debit_account', '1461')['amount_cents']);
        $this->create($preview)->assertCreated();
    }

    public function test_same_account_with_two_rates_produces_separate_records(): void
    {
        $id = $this->approved();
        $document = AccountingDocument::find($id);
        $beer = AccountingAccount::where('account_number', '5132')->first();
        $sirup = AccountingAccount::where('account_number', '5110')->first();
        $document->lines()->where('account_id', $sirup->id)->update(['account_id' => $beer->id, 'account_number_snapshot' => '5132']);
        $rows = collect($this->preview()['rows'])->where('debit_account', '5132');
        $this->assertCount(2, $rows);
        $this->assertSame(['8', '9'], $rows->pluck('tax_key')->sort()->values()->all());
    }

    public function test_mixed_approved_and_review_selection_is_rejected_atomically(): void
    {
        $id = $this->approved();
        $preview = $this->preview();
        $other = AccountingDocument::create(['status' => 'review', 'invoice_date' => '2026-06-17']);
        $this->postJson('/api/accounting/exports', ['year' => 2026, 'document_ids' => [$id, $other->id], 'fingerprint' => $preview['fingerprint']])->assertUnprocessable();
        $this->assertDatabaseCount('accounting_export_batches', 0);
        $this->assertSame('approved', AccountingDocument::find($id)->status);
    }

    public function test_every_supplier_gets_one_invoice_sized_creditor_posting_even_with_same_invoice_number(): void
    {
        $original = AccountingDocument::find($this->approved());
        foreach (['70012', '70013', '70007'] as $number) {
            $supplier = \App\Models\AccountingSupplier::where('creditor_account', $number)->firstOrFail();
            $copy = $original->replicate();
            $copy->source_reference = 'copy-'.$number;
            $copy->supplier_id = $supplier->id;
            $copy->supplier_name_raw = $supplier->name;
            $copy->save();
            foreach ($original->lines as $line) {
                $newLine = $line->replicate();
                $newLine->document_id = $copy->id;
                $newLine->save();
            }
        }
        $preview = $this->preview();
        $this->assertCount(4, $preview['documents']);
        $this->assertSame(129924, $preview['total_cents']);
        $this->assertSame(28, $preview['row_count']);
        foreach (AccountingDocument::all() as $doc) {
            $this->assertSingleCreditor($preview['rows'], $doc);
        }
        $this->create($preview)->assertCreated();
    }

    public function test_clearing_account_must_be_explicit_active_dedicated_and_not_a_posting_account(): void
    {
        $id = $this->approved();
        foreach ([null, '', 'unknown', '9999999999', '9998', '1141', '1461', '1462', '1600', '1800', '70007', '5110'] as $bad) {
            config(['accounting.lexware_clearing_account' => $bad]);
            $this->postJson('/api/accounting/exports/preview', ['year' => 2026])->assertUnprocessable();
        }
        config(['accounting.lexware_clearing_account' => '1599']);
        $preview = $this->preview();
        AccountingAccount::where('account_number', '1599')->update(['active' => false]);
        $this->create($preview)->assertUnprocessable();
        AccountingAccount::where('account_number', '1599')->update(['active' => true, 'kind' => 'expense']);
        $this->postJson('/api/accounting/exports/preview', ['year' => 2026])->assertUnprocessable();
        // Even a mislabelled supplier account and a posted account cannot be used as the bridge.
        AccountingAccount::create(['account_number' => '70007', 'name' => 'Wrong', 'kind' => 'interim', 'active' => true]);
        config(['accounting.lexware_clearing_account' => '70007']);
        $this->postJson('/api/accounting/exports/preview', ['year' => 2026])->assertUnprocessable();
        AccountingAccount::where('account_number', '5110')->update(['kind' => 'interim']);
        config(['accounting.lexware_clearing_account' => '5110']);
        $this->postJson('/api/accounting/exports/preview', ['year' => 2026])->assertUnprocessable();
        $this->assertDatabaseCount('accounting_export_batches', 0);
        $this->assertSame('approved', AccountingDocument::find($id)->status);
    }

    public function test_changing_clearing_account_invalidates_preview_and_cannot_change_old_download(): void
    {
        $this->approved();
        $preview = $this->preview();
        AccountingAccount::create(['account_number' => '1598', 'name' => 'Second test clearing', 'kind' => 'interim', 'active' => true]);
        config(['accounting.lexware_clearing_account' => '1598']);
        $this->create($preview)->assertStatus(409);
        $this->assertDatabaseCount('accounting_export_batches', 0);
        $newPreview = $this->preview();
        $this->assertNotSame($preview['fingerprint'], $newPreview['fingerprint']);
        $result = $this->create($newPreview)->assertCreated();
        $csv = $this->get($result->json('download_url'))->getContent();
        $this->assertSame('1598', AccountingExportBatch::first()->metadata['clearing_account']);
        config(['accounting.lexware_clearing_account' => null]);
        $this->assertSame($csv, $this->get($result->json('download_url'))->assertOk()->getContent());
    }

    public function test_a_single_group_invoice_still_has_exact_invoice_text_on_only_creditor_movement(): void
    {
        $doc = AccountingDocument::find($this->approved());
        $doc->lines()->delete();
        $doc->lines()->create(['position_no' => 1, 'description' => 'Service',
            'account_id' => AccountingAccount::where('account_number', '5110')->value('id'),
            'status' => 'proposed', 'net_cents' => 10000, 'tax_cents' => 700, 'gross_cents' => 10700, 'tax_rate' => 7]);
        $doc->update(['invoice_number' => '000123-A', 'net_cents' => 10000, 'tax_cents' => 700, 'gross_cents' => 10700, 'raw_payload' => null]);
        $preview = $this->preview();
        $this->assertSame(2, $preview['row_count']);
        $this->assertSingleCreditor($preview['rows'], $doc);
        $this->assertSame('000123-A', $preview['rows'][0]['text']);
        $this->assertStringContainsString('Getränke Gato', $preview['rows'][1]['text']);
    }

    public function test_zero_total_invoice_cannot_create_a_zero_amount_open_item(): void
    {
        $doc = AccountingDocument::find($this->approved());
        $doc->lines()->delete();
        foreach (['5110' => 100, '5131' => -100] as $account => $amount) {
            $doc->lines()->create(['description' => 'Offset', 'account_id' => AccountingAccount::where('account_number', $account)->value('id'),
                'status' => 'proposed', 'net_cents' => $amount, 'tax_cents' => 0, 'gross_cents' => $amount, 'tax_rate' => 0]);
        }
        $doc->update(['net_cents' => 0, 'tax_cents' => 0, 'gross_cents' => 0, 'raw_payload' => null]);
        $this->postJson('/api/accounting/exports/preview', ['year' => 2026])->assertUnprocessable();
        $this->assertDatabaseCount('accounting_export_batches', 0);
    }

    public function test_old_v1_batch_download_is_preserved_when_new_format_is_configured(): void
    {
        $csv = "Belegdatum;Buchungstext\r\n17.06.2026;Legacy\r\n";
        $batch = AccountingExportBatch::create(['status' => 'generated', 'filename' => 'legacy.csv', 'row_count' => 1, 'total_cents' => 1,
            'metadata' => ['format' => 'lexware_ascii_ansi_v1', 'csv_base64' => base64_encode($csv), 'sha256' => hash('sha256', $csv)]]);
        config(['accounting.lexware_clearing_account' => null]);
        $this->assertSame($csv, $this->get('/api/accounting/exports/'.$batch->id.'/download')->assertOk()->getContent());
    }
}
