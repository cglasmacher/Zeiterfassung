<?php

namespace Tests\Feature\Cash;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashDocumentExportTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'cash-pdf-export-test-token-32-characters';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cash.archive.export_token' => $this->token]);
    }

    private function openDay(): array
    {
        $this->actingAs(User::factory()->create());

        return $this->postJson('/api/cash/days', ['business_date' => '2026-10-16', 'opening' => '250.00'])->assertOk()->json();
    }

    private function confirm(array $detail, string $requestId): array
    {
        return $this->postJson('/api/cash/days/'.$detail['day']['id'].'/handovers', [
            'revision' => $detail['day']['revision'], 'request_id' => $requestId, 'label' => 'Service',
            'reports' => [['operator' => '1', 'reference' => 'Seit letzter Übergabe', 'cash_sales' => '100.00', 'cash_due' => '100.00', 'card_sales' => '0.00', 'card_due' => '0.00']],
            'workers' => [], 'movements' => [], 'counted' => '350.00', 'envelope' => '100.00', 'note' => '',
        ])->assertOk()->json();
    }

    public function test_export_requires_its_own_token_and_fails_closed(): void
    {
        config(['cash.archive.export_token' => null]);
        $this->getJson('/api/integrations/cash-documents')->assertStatus(503);
        config(['cash.archive.export_token' => $this->token]);
        $this->getJson('/api/integrations/cash-documents')->assertUnauthorized();
        $this->withToken('wrong')->getJson('/api/integrations/cash-documents')->assertUnauthorized();
        $this->withToken($this->token)->getJson('/api/integrations/cash-documents')->assertOk()->assertJsonCount(0, 'documents');
    }

    public function test_confirmed_handovers_have_stable_non_overwriting_names_and_real_pdfs(): void
    {
        $detail = $this->openDay();
        $requestId = (string) Str::uuid();
        $first = $this->confirm($detail, $requestId);
        $this->confirm($detail, $requestId); // Retried confirmation must remain one document.
        $second = $this->confirm($first, (string) Str::uuid());
        $response = $this->withToken($this->token)->getJson('/api/integrations/cash-documents')->assertOk()->assertJsonCount(2, 'documents');
        $this->assertSame('2026.10.16-Schicht.pdf', $response->json('documents.0.filename'));
        $this->assertSame('2026/10/2026.10.16-Schicht-02.pdf', $response->json('documents.1.relative_path'));
        $pdf = $this->get($response->json('documents.0.download_path'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertGreaterThan(10000, strlen($pdf->getContent()));
        $id = $first['handovers'][0]['id'];
        $ack = '/api/integrations/cash-documents/handover/'.$id.'/ack';
        $payload = ['remote_path' => '/share/Buchhaltung/Kassendoku/2026/10/2026.10.16-Schicht.pdf'];
        $this->postJson($ack, $payload)->assertOk()->assertJsonPath('accepted', true);
        $this->postJson($ack, $payload)->assertOk();
        $this->assertDatabaseCount('cash_document_archives', 1);
        $this->getJson('/api/integrations/cash-documents')->assertOk()->assertJsonCount(1, 'documents')
            ->assertJsonPath('documents.0.filename', '2026.10.16-Schicht-02.pdf');
        $this->assertDatabaseCount('cash_handovers', 2);
        $this->assertDatabaseHas('cash_days', ['id' => $second['day']['id'], 'status' => 'draft']);
        $this->assertDatabaseHas('cash_register_states', ['id' => 1, 'balance_cents' => 25000]);
    }

    public function test_daily_document_is_only_exportable_after_finalization(): void
    {
        $detail = $this->openDay();
        $id = $detail['day']['id'];
        $this->withToken($this->token)->get('/api/integrations/cash-documents/day/'.$id.'/pdf')->assertNotFound();
        $this->postJson('/api/integrations/cash-documents/day/'.$id.'/ack', ['remote_path' => '/test'])->assertNotFound();
        $this->getJson('/api/integrations/cash-documents')->assertOk()->assertJsonCount(0, 'documents');
        $this->putJson('/api/cash/days/'.$id, [
            'revision' => $detail['day']['revision'], 'workers' => [], 'movements' => [], 'counted' => '350.00', 'envelope' => '100.00', 'note' => '',
            'z' => ['number' => '291', 'register' => 'Kasse #1', 'reference' => 'Z-291',
                'period_start' => '2026-10-16T06:00', 'period_end' => '2026-10-17T06:00',
                'net7' => '93.46', 'tax7' => '6.54', 'gross7' => '100.00', 'net19' => '0.00', 'tax19' => '0.00', 'gross19' => '0.00',
                'cash_sales' => '100.00', 'cash_due' => '100.00', 'card_sales' => '0.00', 'card_due' => '0.00'],
        ])->assertOk();
        $this->getJson('/api/integrations/cash-documents')->assertOk()->assertJsonCount(0, 'documents');
        $this->postJson('/api/cash/days/'.$id.'/finalize', ['revision' => $detail['day']['revision'] + 1])->assertOk();
        $document = $this->getJson('/api/integrations/cash-documents')->assertOk()->assertJsonCount(1, 'documents')->json('documents.0');
        $this->assertSame('2026.10.16-Tag.pdf', $document['filename']);
        $this->assertStringStartsWith('%PDF-', $this->get($document['download_path'])->assertOk()->getContent());
        $this->assertDatabaseCount('cash_deliveries', 1); // Accounting export is separate.
        $this->assertSame('pending', DB::table('cash_deliveries')->value('status'));
    }
}
