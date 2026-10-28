<?php

namespace Tests\Feature\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingDocument;
use App\Models\AccountingRule;
use App\Models\AccountingSupplier;
use App\Models\User;
use App\Services\Accounting\HandelshofInvoiceParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class HandelshofImportTest extends \Tests\TestCase
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

    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/Fixtures/accounting/handelshof-'.$name));
    }

    private function fakePdf(?string $xml = null, ?string $layout = null): void
    {
        Process::fake(function ($process) use ($xml, $layout) {
            if (in_array('-savefile', $process->command, true)) {
                file_put_contents($process->command[array_search('-o', $process->command) + 1], $xml ?? $this->fixture('factur-x.xml'));

                return Process::result();
            }

            return Process::result(output: $layout ?? $this->fixture('layout.txt'));
        });
    }

    private function upload(string $reference = 'handelshof-test', array $extra = [])
    {
        return $this->withToken('accounting-test-import-token-at-least-32-characters')->post('/api/integrations/accounting/documents', [
            'payload' => json_encode([...$extra, 'source_reference' => $reference, 'extraction_profile' => 'handelshof_facturx_v1']),
            'file' => UploadedFile::fake()->createWithContent('handelshof.pdf', "%PDF-1.4\n%handelshof-test\n%%EOF"),
        ], ['Accept' => 'application/json']);
    }

    public function test_real_invoice_structure_matches_all_printed_positions_and_totals(): void
    {
        $data = (new HandelshofInvoiceParser)->parse($this->fixture('factur-x.xml'), $this->fixture('layout.txt'));
        $this->assertSame('TEST-HH-41922', $data['invoice_number']);
        $this->assertSame('2026-07-01', $data['invoice_date']);
        $this->assertSame(136633, $data['net_cents']);
        $this->assertSame(11486, $data['tax_cents']);
        $this->assertSame(148119, $data['gross_cents']);
        $this->assertCount(58, $data['parser_evidence']['positions']);
        $this->assertCount(55, $data['lines']);
        $this->assertSame([3, 23, 26], array_values(array_diff(range(1, 58), array_column($data['lines'], 'position_no'))));
        $deposits = array_filter($data['lines'], fn ($line) => $line['invoice_group'] === 'Pfand/Leergut');
        $this->assertCount(10, $deposits);
        $this->assertSame(7992, array_sum(array_column($deposits, 'net_cents')));
        $this->assertSame(1518, array_sum(array_column($deposits, 'tax_cents')));
        $this->assertSame(-12000, collect($data['lines'])->firstWhere('position_no', 47)['net_cents']);
        foreach ([7 => [120615, 8443], 19 => [16018, 3043]] as $rate => [$net, $tax]) {
            $lines = array_filter($data['lines'], fn ($line) => $line['tax_rate'] === $rate);
            $this->assertSame($net, array_sum(array_column($lines, 'net_cents')));
            $this->assertSame($tax, array_sum(array_column($lines, 'tax_cents')));
        }
    }

    public function test_import_preserves_original_groups_accounts_and_exact_pfand_split(): void
    {
        $this->fakePdf();
        $id = $this->upload()->assertCreated()->assertJsonPath('document.status', 'ready')->json('document.id');
        $doc = AccountingDocument::find($id);
        $this->assertSame('70012', $doc->supplier->creditor_account);
        $this->assertSame('Handelshof Köln Stiftung & Co. KG', $doc->supplier_name_raw);
        $this->assertSame(hash('sha256', $this->fixture('factur-x.xml')), $doc->raw_payload['xml_sha256']);
        $this->assertSame("%PDF-1.4\n%handelshof-test\n%%EOF", Storage::get($doc->file_path));
        $this->assertCount(55, $doc->lines);
        $this->assertSame(0, $doc->lines->whereNull('account_id')->count());
        $this->assertSame('5110', $doc->lines->firstWhere('article_number', '127064')->account_number_snapshot);
        $this->assertSame('19.00', $doc->lines->firstWhere('article_number', '127064')->tax_rate);
        foreach (['112158' => '5113', '100077' => '5111', '102190' => '5110', '137654' => '5131',
            '167506' => '5131', '713346' => '6301', '337004' => '1141', '336937' => '5131',
            '338564' => '5131', '315310' => '5134'] as $article => $account) {
            $line = $doc->lines->firstWhere('article_number', (string) $article);
            $this->assertSame($account, $line->account_number_snapshot);
            $this->assertSame('supplier_rule', $line->classification_source);
        }
        $this->actingAs(User::factory()->create());
        $detail = $this->getJson('/api/accounting/documents/'.$id)->assertOk()->assertJsonPath('booking_summary.unresolved_lines', 0);
        $this->assertCount(2, collect($detail->json('booking_summary.rows'))->where('account_number', '5110'));
        $postings = collect($detail->json('booking_summary.deposit_postings.rows'))->keyBy('account_number');
        $this->assertSame(7992, $postings['1141']['debit_cents']);
        $this->assertSame(1518, $postings['1461']['debit_cents']);
        $this->assertSame(9510, $postings['70012']['credit_cents']);
        $this->postJson('/api/accounting/documents/'.$id.'/approve')->assertOk();
        $preview = $this->postJson('/api/accounting/exports/preview', ['year' => 2026, 'document_ids' => [$id]])
            ->assertOk()->assertJsonPath('row_count', 11)->assertJsonPath('total_cents', 148119)->json();
        $creditorRows = collect($preview['rows'])->filter(fn ($r) => $r['debit_account'] === '70012' || $r['credit_account'] === '70012');
        $this->assertCount(1, $creditorRows);
        $this->assertSame(148119, $creditorRows->first()['amount_cents']);
        $this->assertSame('TEST-HH-41922', $creditorRows->first()['text']);
        $this->assertSame('', $creditorRows->first()['tax_key']);
        $ice = collect($preview['rows'])->first(fn ($r) => $r['debit_account'] === '5110' && $r['tax_key'] === '9');
        $this->assertSame(4329, $ice['amount_cents']);
        $batch = $this->postJson('/api/accounting/exports', ['year' => 2026, 'document_ids' => [$id], 'fingerprint' => $preview['fingerprint']])
            ->assertCreated()->json();
        $this->get($batch['download_url'])->assertOk();
        $this->upload()->assertOk()->assertJsonPath('duplicate', true);
        $this->assertDatabaseCount('accounting_documents', 1);
    }

    public function test_learned_article_rule_wins_without_changing_invoice_tax_and_export_groups(): void
    {
        $supplier = AccountingSupplier::where('creditor_account', '70012')->first();
        foreach (['127064' => '5131', '112158' => '5110'] as $article => $number) {
            AccountingRule::create(['supplier_id' => $supplier->id, 'match_type' => 'article_number',
                'match_value' => $article, 'account_id' => AccountingAccount::where('account_number', $number)->value('id'),
                'tax_rate' => 0, 'priority' => 999, 'learned_from_manual' => true, 'active' => true]);
        }
        $this->fakePdf();
        $id = $this->upload()->assertCreated()->assertJsonPath('document.status', 'ready')->json('document.id');
        $doc = AccountingDocument::find($id);
        $tomato = $doc->lines()->where('article_number', '112158')->first();
        $this->assertSame('5110', $tomato->account_number_snapshot);
        $this->assertSame('learned_rule', $tomato->classification_source);
        $this->assertSame('7.00', $tomato->tax_rate);
        $this->assertSame('19.00', $doc->lines()->where('article_number', '127064')->first()->tax_rate);
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/accounting/documents/'.$id.'/approve')->assertOk();
        $preview = $this->postJson('/api/accounting/exports/preview', ['year' => 2026, 'document_ids' => [$id]])->assertOk();
        $this->assertLessThan(55, $preview->json('row_count'));
        $rows = collect($preview->json('rows'));
        // No tax key on Pfand or its explicit input VAT; only net on 1141.
        $this->assertSame(7992, $rows->firstWhere('debit_account', '1141')['amount_cents']);
        $this->assertSame(1518, $rows->firstWhere('debit_account', '1461')['amount_cents']);
    }

    public function test_invalid_xml_or_pdf_never_falls_back_to_client_or_ai_amounts(): void
    {
        $this->fakePdf('<bad/>');
        $this->upload(extra: ['invoice_number' => 'invented', 'gross_cents' => 1,
            'lines' => [['description' => 'AI', 'account_number' => '5110', 'confidence' => 100]]])
            ->assertCreated()->assertJsonPath('document.status', 'analysis_failed')->assertJsonPath('document.gross_cents', null);
        $doc = AccountingDocument::first();
        $this->assertNull($doc->invoice_number);
        $this->assertNotEmpty($doc->file_path);
        $this->assertTrue($doc->raw_payload['analysis_failed']);
        $this->assertCount(1, $doc->lines);
        $this->assertNull($doc->lines->first()->account_id);
    }

    public function test_missing_pdfdetach_saves_original_as_analysis_failed_and_cleans_directory(): void
    {
        $directory = null;
        Process::fake(function ($process) use (&$directory) {
            $directory = dirname($process->command[array_search('-o', $process->command) + 1]);

            return Process::result(errorOutput: 'not found', exitCode: 127);
        });
        $this->upload()->assertCreated()->assertJsonPath('document.status', 'analysis_failed');
        $this->assertDirectoryDoesNotExist($directory);
        Process::assertRan(fn ($p) => $p->command[0] === '/usr/bin/pdfdetach' && $p->timeout === 20);
    }

    public function test_tax_groups_are_revalidated_after_manual_changes(): void
    {
        $this->fakePdf();
        $id = $this->upload()->assertCreated()->json('document.id');
        $doc = AccountingDocument::find($id);
        $doc->lines()->where('article_number', '137654')->update(['tax_rate' => 7]);
        $errors = app(\App\Services\Accounting\DocumentValidation::class)->errors($doc);
        $this->assertContains('Handelshof-Steuergruppe 7 % stimmt nicht mit dem Original überein.', $errors);
        $this->assertContains('Handelshof-Steuergruppe 19 % stimmt nicht mit dem Original überein.', $errors);
    }

    public function test_inactive_supplier_or_accounts_cannot_be_silently_substituted(): void
    {
        $this->fakePdf();
        AccountingAccount::where('account_number', '5111')->update(['active' => false]);
        $id = $this->upload()->assertCreated()->assertJsonPath('document.status', 'review')->json('document.id');
        $this->assertNull(AccountingDocument::find($id)->lines()->where('article_number', '100077')->first()->account_id);
        AccountingSupplier::where('creditor_account', '70012')->update(['active' => false]);
        $this->upload('inactive')->assertUnprocessable();
    }

    public function test_content_inspection_ignores_forwarding_sender_and_requires_supplier_identifiers(): void
    {
        Process::fake(fn () => Process::result(output: $this->fixture('layout.txt')));
        $result = app(\App\Services\Accounting\InvoiceSupplierDetector::class)->detect('/tmp/test.pdf');
        $this->assertSame('handelshof', $result['supplier']);
        $this->assertSame('handelshof_facturx_v1', $result['profile']);
        Process::fake(fn () => Process::result(output: 'Handelshof Köln Stiftung & Co. KG ohne USt-ID'));
        $this->assertNull(app(\App\Services\Accounting\InvoiceSupplierDetector::class)->detect('/tmp/test.pdf')['supplier']);
    }

    public function test_bar_ice_uses_5110_even_for_another_article_number_without_inferring_tax(): void
    {
        $account = new \App\Services\Accounting\HandelshofAccount;
        foreach (['5KG VOLLEIS-WUERFEL SOLID CUBES', '2KG Eiswürfel'] as $description) {
            $result = $account->resolve(['article_number' => '999999', 'invoice_group' => 'Tiefkühlkost',
                'description' => $description, 'tax_rate' => 19]);
            $this->assertSame('5110', $result[0]);
        }
        $this->assertNull($account->resolve(['article_number' => '999999', 'invoice_group' => 'Unbekannt', 'tax_rate' => 19]));
    }

    public function test_corrupt_or_unsupported_data_is_blocked(): void
    {
        $xml = $this->fixture('factur-x.xml');
        $pdf = $this->fixture('layout.txt');
        $cases = [
            [str_replace('<ram:LineID>2</ram:LineID>', '<ram:LineID>1</ram:LineID>', $xml), $pdf],
            [str_replace('<ram:LineTotalAmount>20.12', '<ram:LineTotalAmount>20.13', $xml), $pdf],
            [str_replace('DE123046183', 'DE999999999', $xml), $pdf],
            [str_replace('>380<', '>381<', $xml), $pdf],
            [str_replace('>EUR<', '>USD<', $xml), $pdf],
            [str_replace('>20260701<', '>20260230<', $xml), $pdf],
            [str_replace('>7.00<', '>0.00<', $xml), $pdf],
            [str_replace('<ram:ChargeTotalAmount>0.00', '<ram:ChargeTotalAmount>1.00', $xml), $pdf],
            [str_replace('<ram:GrandTotalAmount>1481.19', '<ram:GrandTotalAmount>1481.20', $xml), $pdf],
            [str_replace('<ram:CalculatedAmount>84.43', '<ram:CalculatedAmount>84.44', $xml), $pdf],
            [str_replace('<ram:BasisQuantity unitCode="C62">1', '<ram:BasisQuantity unitCode="C62">2', $xml), $pdf],
            [$xml, str_replace('20,12', '20,13', $pdf)],
            [$xml, str_replace('TEST-HH-41922', 'OTHER', $pdf)],
            [$xml, preg_replace('/^.*337004.*$/m', '', $pdf)],
            [$xml, str_replace('Seite:      2', 'Seite:      3', $pdf)],
            [$xml, str_replace('(19,0%=1)', '(19,0%=2)', $pdf)],
            [$xml, str_replace('1481,19', '1481,20', $pdf)],
            [str_replace('<ram:Value>Obst/Gemüse</ram:Value>', '<ram:Value>Feinkost/Mopro</ram:Value>', $xml), $pdf],
            [str_replace('<rsm:CrossIndustryInvoice', '<!DOCTYPE x [<!ENTITY ext SYSTEM "file:///etc/passwd">]><rsm:CrossIndustryInvoice', $xml), $pdf],
        ];
        foreach ($cases as $i => [$badXml, $badPdf]) {
            try {
                (new HandelshofInvoiceParser)->parse($badXml, $badPdf);
                $this->fail('Invalid invoice passed: '.$i);
            } catch (\RuntimeException $e) {
                $this->assertStringStartsWith('Handelshof-Analyse:', $e->getMessage());
            }
        }
    }
}
