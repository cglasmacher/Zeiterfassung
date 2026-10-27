<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingDocument;
use App\Models\AccountingRule;
use App\Models\AccountingSupplier;
use App\Services\Accounting\GatoInvoiceParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;

class GatoImportTest extends \Tests\TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

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
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['accounting.import_token' => 'accounting-test-import-token-at-least-32-characters']);
        AccountingSupplier::create(['name' => 'Getränke Gato GmbH', 'creditor_account' => '70007', 'active' => true]);
        foreach (['5132', '5133'] as $number) {
            AccountingAccount::create(['account_number' => $number, 'name' => $number, 'active' => true]);
        }
    }

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/accounting/gato-layout.txt'));
    }

    private function gatoUpload()
    {
        return $this->withToken('accounting-test-import-token-at-least-32-characters')->post('/api/integrations/accounting/documents', [
            'payload' => json_encode(['source_reference' => 'gato-fixture', 'supplier_name' => 'Getränke Gato GmbH', 'extraction_profile' => 'gato_layout_v1']),
            'file' => UploadedFile::fake()->createWithContent('gato.pdf', "%PDF-1.4\n%test\n%%EOF"),
        ], ['Accept' => 'application/json']);
    }

    public function test_gato_invoice_parses_exact_totals_articles_and_signed_deposits(): void
    {
        $d = (new GatoInvoiceParser)->parse($this->fixture());
        $this->assertSame(32481, $d['gross_cents']);
        $this->assertSame(27594, $d['net_cents']);
        $this->assertSame(4887, $d['tax_cents']);
        $this->assertCount(20, $d['lines']);
        $this->assertSame('04205', $d['lines'][0]['article_number']);
        $this->assertStringContainsString('Collina', $d['lines'][0]['description']);
        $this->assertSame(-238, array_sum(array_column(array_slice($d['lines'], 12), 'net_cents')));
        $this->assertCount(9, $d['parser_evidence']['deposits']);
        foreach ([7 => [2962, 207], 19 => [24632, 4680]] as $rate => [$net, $tax]) {
            $lines = array_filter($d['lines'], fn ($l) => $l['tax_rate'] === $rate);
            $this->assertSame($net, array_sum(array_column($lines, 'net_cents')));
            $this->assertSame($tax, array_sum(array_column($lines, 'tax_cents')));
        }
    }

    public function test_missing_page_lost_star_and_changed_amount_fail_closed(): void
    {
        foreach ([
            explode("\f", $this->fixture())[0],
            str_replace('7,69    *', '7,69', $this->fixture()),
            str_replace('101,70', '101,71', $this->fixture()),
            str_replace('Seite 2/2', 'Seite 2/3', $this->fixture()),
        ] as $bad) {
            try {
                (new GatoInvoiceParser)->parse($bad);
                $this->fail('Malformed invoice must not pass.');
            } catch (\RuntimeException $e) {
                $this->assertStringStartsWith('Gato-Analyse:', $e->getMessage());
            }
        }
    }

    public function test_portal_classifies_real_layout_without_ai_and_keeps_rule_precedence(): void
    {
        foreach (['1141', '5110', '5131'] as $number) {
            AccountingAccount::create(['account_number' => $number, 'name' => $number, 'active' => true]);
        }
        Process::fake(fn () => Process::result(output: $this->fixture()));
        $response = $this->gatoUpload()->assertCreated()->assertJsonPath('document.status', 'ready')
            ->assertJsonPath('document.gross_cents', 32481);
        $doc = AccountingDocument::find($response->json('document.id'));
        $this->assertSame('5132', $doc->lines()->where('article_number', '11388')->first()->account_number_snapshot);
        $this->assertSame('5131', $doc->lines()->where('article_number', '80400')->first()->account_number_snapshot);
        $this->assertSame('gato_layout_v1', $doc->raw_payload['gato']['parser']);
        $this->actingAs(\App\Models\User::factory()->create());
        $detail = $this->getJson('/api/accounting/documents/'.$doc->id)->assertOk()
            ->assertJsonPath('blocking_errors', [])->assertJsonPath('booking_summary.unresolved_lines', 0)
            ->assertJsonCount(5, 'booking_summary.rows')->assertJsonPath('document.supplier.active', true);
        $rows = collect($detail->json('booking_summary.rows'))->keyBy('account_number');
        $this->assertSame(-238, $rows['1141']['net_cents']);
        $this->assertSame(-45, $rows['1141']['tax_cents']);
        $this->assertSame(11312, $rows['5131']['net_cents']);
        $this->assertSame(32481, $rows->sum('gross_cents'));
        $this->gatoUpload()->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('accounting_documents', 1);

        AccountingRule::create(['supplier_id' => AccountingSupplier::first()->id,
            'match_type' => 'article_number', 'match_value' => '11388',
            'account_id' => AccountingAccount::where('account_number', '5133')->first()->id,
            'learned_from_manual' => true, 'active' => true, 'priority' => 999]);
        // Different reference to exercise new resolution; retry must preserve the old original.
        $this->withToken('accounting-test-import-token-at-least-32-characters')->post('/api/integrations/accounting/documents', [
            'payload' => json_encode(['source_reference' => 'gato-second', 'supplier_name' => 'Getränke Gato GmbH', 'extraction_profile' => 'gato_layout_v1']),
            'file' => UploadedFile::fake()->createWithContent('gato.pdf', "%PDF-1.4\n%test\n%%EOF"),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('5133', AccountingDocument::latest('id')->first()->lines()->where('article_number', '11388')->first()->account_number_snapshot);
    }

    public function test_failed_pdf_analysis_is_saved_with_original_and_no_ai_amounts(): void
    {
        Process::fake(fn () => Process::result(output: 'unreadable'));
        $response = $this->gatoUpload()->assertCreated()->assertJsonPath('document.status', 'analysis_failed')
            ->assertJsonPath('document.gross_cents', null);
        $doc = AccountingDocument::find($response->json('document.id'));
        $this->assertTrue($doc->raw_payload['analysis_failed']);
        $this->assertNotEmpty($doc->file_path);
        $this->assertNotEmpty($doc->validation_errors);
    }

    public function test_unrecognized_article_remains_for_review_instead_of_inventing_account(): void
    {
        foreach (['1141', '5110', '5131'] as $number) {
            AccountingAccount::create(['account_number' => $number, 'name' => $number, 'active' => true]);
        }
        $text = str_replace("04205    CONCA d'ORO Prosecco Frizzante", '09999    Unbekanntes Produkt', $this->fixture());
        Process::fake(fn () => Process::result(output: $text));
        $response = $this->gatoUpload()->assertCreated()->assertJsonPath('document.status', 'review');
        $line = AccountingDocument::find($response->json('document.id'))->lines()->where('article_number', '09999')->first();
        $this->assertNull($line->account_id);
        $this->assertSame('unresolved', $line->classification_source);
    }

    public function test_missing_pdftotext_is_visible_analysis_failure(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'not found', exitCode: 127));
        $this->gatoUpload()->assertCreated()->assertJsonPath('document.status', 'analysis_failed');
        Process::assertRan(fn ($process) => $process->command[0] === '/usr/bin/pdftotext'
            && array_slice($process->command, 1, 3) === ['-layout', '-enc', 'UTF-8'] && $process->timeout === 20);
    }

    public function test_pdf_text_identifies_supplier_without_mail_sender(): void
    {
        Process::fake(fn () => Process::result(output: 'Getränke Gato GmbH www.getraenke-gato.de Rechnung'));
        $this->withToken('accounting-test-import-token-at-least-32-characters')->post('/api/integrations/accounting/inspect', [
            'file' => UploadedFile::fake()->createWithContent('gato.pdf', "%PDF-1.4\n%test\n%%EOF"),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('supplier', 'gato')
            ->assertJsonPath('evidence', 'pdf_supplier_identifiers');
        $this->assertDatabaseCount('accounting_documents', 0);
        $this->postJson('/api/integrations/accounting/inspect')->assertUnprocessable();
        $this->withToken('wrong')->postJson('/api/integrations/accounting/inspect')->assertUnauthorized();
    }

    public function test_known_embedded_letterhead_identifies_gato_and_temp_image_is_removed(): void
    {
        $bytes = 'test-letterhead-jpeg';
        config(['accounting.gato_letterhead_hashes' => [hash('sha256', $bytes)]]);
        $image = null;
        Process::fake(function ($process) use ($bytes, &$image) {
            if (in_array('-list', $process->command, true)) {
                return Process::result(output: '1 0 image 2457 3475 icc 3 8 jpeg no 32 0');
            }
            if (in_array('-j', $process->command, true)) {
                $image = end($process->command).'-000.jpg';
                file_put_contents($image, $bytes);
            }

            return Process::result(output: '');
        });
        $result = app(\App\Services\Accounting\InvoiceSupplierDetector::class)->detect('/tmp/input.pdf');
        $this->assertSame('gato', $result['supplier']);
        $this->assertNotNull($image);
        $this->assertFileDoesNotExist($image);
        $this->assertDirectoryDoesNotExist(dirname($image));
    }

    public function test_unknown_pdf_is_never_forced_to_gato(): void
    {
        Process::fake(fn () => Process::result(output: 'Other supplier, invoice 100'));
        $result = app(\App\Services\Accounting\InvoiceSupplierDetector::class)->detect('/tmp/input.pdf');
        $this->assertNull($result['supplier']);
        $this->assertNull($result['profile']);
    }

    public function test_summary_keeps_different_tax_rates_separate_on_same_account(): void
    {
        foreach (['1141', '5110', '5131'] as $number) {
            AccountingAccount::create(['account_number' => $number, 'name' => $number, 'active' => true]);
        }
        Process::fake(fn () => Process::result(output: $this->fixture()));
        $id = $this->gatoUpload()->assertCreated()->json('document.id');
        $document = AccountingDocument::find($id);
        $beer = AccountingAccount::where('account_number', '5132')->first();
        $document->lines()->where('article_number', '59054')->update(['account_id' => $beer->id, 'account_number_snapshot' => '5132']);
        $rows = collect(app(\App\Services\Accounting\BookingSummary::class)->forDocument($document)['rows']);
        $this->assertCount(2, $rows->where('account_number', '5132'));
        $this->assertSame(32481, $rows->sum('gross_cents'));
        $beer->update(['active' => false]);
        $document->unsetRelation('lines');
        $this->assertSame(2, app(\App\Services\Accounting\BookingSummary::class)->forDocument($document)['unresolved_lines']);
    }
}
