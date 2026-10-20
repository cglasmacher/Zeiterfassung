<?php

namespace Tests\Feature\Cash;

use App\Models\User;
use App\Services\Cash\ZReportMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ZReportImportTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-z-report-token-with-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cash.z_reports.import_token' => $this->token, 'cash.z_reports.previous_day_until' => '11:00']);
        Storage::fake('local');
    }

    private function text(string $date = '16.10.26', string $time = '23:21'): string
    {
        return "Finanzbericht <Z1>\nZ-Zähler\nFinanzbericht 1 291\nLetzte Nullst. 15.10.26 22:01\nKasse #1 1\n$date $time\n";
    }

    private function upload(array $overrides = [])
    {
        return $this->withToken($this->token)->post('/api/integrations/vectron/z-reports', array_replace([
            'file' => UploadedFile::fake()->createWithContent('bericht.pdf', "%PDF-1.4\n%test report\n%%EOF"),
            'extracted_text' => $this->text(), 'message_id' => '<test@example.test>',
        ], $overrides), ['Accept' => 'application/json']);
    }

    public function test_ingestion_is_disabled_without_configuration_and_rejects_wrong_token(): void
    {
        config(['cash.z_reports.import_token' => null]);
        $this->postJson('/api/integrations/vectron/z-reports')->assertStatus(503);
        config(['cash.z_reports.import_token' => $this->token]);
        $this->postJson('/api/integrations/vectron/z-reports')->assertUnauthorized();
        $this->withToken('incorrect')->postJson('/api/integrations/vectron/z-reports')->assertUnauthorized();
        $this->assertDatabaseCount('cash_z_reports', 0);
    }

    public function test_pdf_arrives_before_business_day_and_is_accessible_only_after_login(): void
    {
        $response = $this->upload()->assertCreated()->assertJsonPath('business_date', '2026-10-16')->assertJsonPath('needs_assignment', false);
        $id = $response->json('id');
        $this->assertDatabaseCount('cash_days', 0);
        $this->assertDatabaseHas('cash_z_reports', ['id' => $id, 'report_number' => '291', 'business_date' => '2026-10-16']);
        $report = DB::table('cash_z_reports')->find($id);
        Storage::disk('local')->assertExists($report->path);
        $this->getJson('/api/cash/z-reports?date=2026-10-16')->assertUnauthorized();
        $this->getJson("/api/cash/z-reports/$id/pdf")->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/cash/z-reports?date=2026-10-16')->assertOk()
            ->assertJsonCount(1, 'reports')->assertJsonPath('reports.0.report_number', '291')
            ->assertJsonMissingPath('reports.0.path')->assertJsonMissingPath('reports.0.sha256');
        $this->getJson('/api/cash/z-reports?date=2026-10-17')->assertOk()->assertJsonCount(0, 'reports');
        $this->get("/api/cash/z-reports/$id/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_duplicates_do_not_change_manual_assignment_or_create_another_file(): void
    {
        $id = $this->upload()->assertCreated()->json('id');
        $user = User::factory()->create();
        $this->actingAs($user)->patchJson("/api/cash/z-reports/$id/assignment", ['business_date' => '2026-10-15'])->assertOk();
        $this->upload()->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('business_date', '2026-10-15');
        $this->assertDatabaseCount('cash_z_reports', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $history = json_decode(DB::table('cash_z_reports')->value('assignment_history'), true);
        $this->assertSame('2026-10-16', $history[0]['from']);
        $this->assertSame('2026-10-15', $history[0]['to']);
        $this->assertSame($user->id, $history[0]['user_id']);
        $this->assertDatabaseCount('cash_days', 0);
    }

    public function test_non_readable_pdf_is_kept_for_manual_assignment_and_mail_date_is_ignored(): void
    {
        $id = $this->upload(['extracted_text' => '', 'message_id' => '2026-10-18'])->assertCreated()
            ->assertJsonPath('needs_assignment', true)->assertJsonPath('business_date', null)->json('id');
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/cash/z-reports?date=2026-10-18')->assertOk()
            ->assertJsonCount(0, 'reports')->assertJsonCount(1, 'unassigned');
        $this->patchJson("/api/cash/z-reports/$id/assignment", ['business_date' => '2026-10-16'])->assertOk();
        $this->getJson('/api/cash/z-reports?date=2026-10-16')->assertOk()
            ->assertJsonCount(1, 'reports')->assertJsonCount(0, 'unassigned');
    }

    public function test_metadata_handles_midnight_dst_ambiguous_and_unknown_layouts(): void
    {
        $parser = new ZReportMetadata;
        $this->assertSame('2026-10-16', $parser->parse($this->text('17.10.26', '01:20'))['business_date']);
        $this->assertSame('2026-10-17', $parser->parse($this->text('17.10.26', '11:01'))['business_date']);
        $this->assertSame('2026-10-24', $parser->parse($this->text('25.10.26', '01:20'))['business_date']);
        $this->assertSame('2026-10-16', $parser->parse($this->text().$this->text())['business_date']);
        $this->assertNull($parser->parse($this->text().$this->text('17.10.26'))['business_date']);
        $this->assertNull($parser->parse($this->text('31.02.26'))['business_date']);
        $this->assertNull($parser->parse(str_replace('Z1', 'X1', $this->text()))['business_date']);
        $this->assertNull($parser->parse('Letzte Nullst. 15.10.26 22:01')['business_date']);
        config(['cash.z_reports.previous_day_until' => '00:00']);
        $this->assertSame('2026-10-17', $parser->parse($this->text('17.10.26', '01:20'))['business_date']);
    }

    public function test_summary_footer_survives_pdf_whitespace_and_ignores_operator_dates(): void
    {
        $parser = new ZReportMetadata;
        $text = str_replace("\n", ' ', $this->text('20.10.26', '09:15'));
        $text .= ' Bediener (erweitert) <Z1> Kasse #1 1 21.10.26 01:15';
        $parsed = $parser->parse($text);
        $this->assertSame('291', $parsed['report_number']);
        $this->assertSame('2026-10-19', $parsed['business_date']);
        $this->assertSame('2026-10-20 07:15:00', $parsed['reported_at']);
        $this->assertSame('2026-10-19', $parser->parse(str_replace(' ', "\u{00A0}", $text))['business_date']);
        $this->assertSame('2026-10-19', $parser->parse(str_replace("\n20.10.26", "\f20.10.26", $this->text('20.10.26', '09:15')))['business_date']);
        $this->assertNull($parser->parse('Finanzbericht <Z1> Finanzbericht 1 291 Letzte Nullst. 19.10.26 22:01')['business_date']);
    }

    public function test_eleven_oclock_inclusive_and_calendar_boundaries(): void
    {
        $parser = new ZReportMetadata;
        foreach (['00:00', '10:59', '11:00', '11:00:00'] as $time) {
            $this->assertSame('2026-10-19', $parser->parse($this->text('20.10.26', $time))['business_date']);
        }
        foreach (['11:00:01', '11:01', '23:59'] as $time) {
            $this->assertSame('2026-10-20', $parser->parse($this->text('20.10.26', $time))['business_date']);
        }
        $this->assertSame('2025-12-31', $parser->parse($this->text('01.01.26', '09:00'))['business_date']);
        $this->assertSame('2026-09-30', $parser->parse($this->text('01.10.26', '09:00'))['business_date']);
    }

    public function test_reimport_repairs_pending_date_and_returns_z_number_as_report_id(): void
    {
        $id = $this->upload(['extracted_text' => ''])->assertCreated()->json('id');
        $this->upload(['extracted_text' => str_replace("\n", ' ', $this->text('20.10.26', '09:15'))])->assertOk()
            ->assertJsonPath('duplicate', true)->assertJsonPath('record_id', $id)
            ->assertJsonPath('report_id', '291')->assertJsonPath('business_date', '2026-10-19')
            ->assertJsonPath('archive_filename', 'Z-Bericht_2026-10-19_Z-291.pdf');
        $this->assertDatabaseCount('cash_z_reports', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->actingAs(User::factory()->create())->getJson('/api/cash/z-reports?date=2026-10-19')
            ->assertOk()->assertJsonPath('reports.0.report_id', '291');
    }

    public function test_separate_originals_with_same_report_number_do_not_overwrite_each_other(): void
    {
        $this->upload()->assertCreated()->assertJsonPath('archive_filename', 'Z-Bericht_2026-10-16_Z-291.pdf');
        $different = UploadedFile::fake()->createWithContent('other.pdf', "%PDF-1.4\n%different original\n%%EOF");
        $response = $this->upload(['file' => $different])->assertCreated()->assertJsonPath('report_id', '291');
        $this->assertStringContainsString('_Z-291_Beleg-', $response->json('archive_filename'));
        $this->assertDatabaseCount('cash_z_reports', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_invalid_attachments_and_assignment_require_valid_input_and_login(): void
    {
        $this->upload(['file' => UploadedFile::fake()->createWithContent('fake.pdf', '<html>Not a PDF</html>')])->assertUnprocessable();
        $this->patchJson('/api/cash/z-reports/1/assignment', ['business_date' => '2026-10-16'])->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->patchJson('/api/cash/z-reports/1/assignment', ['business_date' => '2026-02-31'])->assertUnprocessable();
        $this->getJson('/api/cash/z-reports?date=invalid')->assertUnprocessable();
        $this->assertDatabaseCount('cash_z_reports', 0);
    }
}
