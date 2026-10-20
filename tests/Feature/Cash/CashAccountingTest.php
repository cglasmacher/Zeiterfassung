<?php

namespace Tests\Feature\Cash;

use App\Jobs\DeliverCashDay;
use App\Models\Employee;
use App\Models\User;
use App\Services\Cash\CashCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function open(string $date = '2026-10-16', string $opening = '250.00'): array
    {
        return $this->postJson('/api/cash/days', ['business_date' => $date, 'opening' => $opening])->assertOk()->json();
    }

    private function handover(array $detail, array $changes = []): array
    {
        return array_replace([
            'revision' => $detail['day']['revision'], 'request_id' => (string) Str::uuid(), 'label' => 'Frühschicht',
            'reports' => [['operator' => '1', 'reference' => 'Seit Tagesbeginn', 'cash_sales' => '500.00', 'cash_due' => '450.00', 'card_sales' => '500.00', 'card_due' => '550.00']],
            'workers' => [], 'movements' => [], 'counted' => '700.00', 'envelope' => '450.00', 'note' => '',
        ], $changes);
    }

    private function closing(array $detail, array $changes = []): array
    {
        return array_replace([
            'revision' => $detail['day']['revision'], 'workers' => [], 'movements' => [], 'counted' => '300.00', 'envelope' => '50.00', 'note' => '',
            'z' => ['number' => '291', 'register' => 'Kasse #1', 'reference' => 'Original Z-291.pdf',
                'period_start' => '2026-10-15T22:01', 'period_end' => '2026-10-16T23:21',
                'net7' => '2574.00', 'tax7' => '180.20', 'gross7' => '2754.20',
                'net19' => '1573.30', 'tax19' => '298.90', 'gross19' => '1872.20',
                'cash_sales' => '1557.40', 'cash_due' => '1393.85', 'card_sales' => '3069.00', 'card_due' => '3232.55',
                'reconciliation_note' => 'Mit Original abgestimmt'],
        ], $changes);
    }

    public function test_shared_balance_handover_and_final_close_do_not_double_count(): void
    {
        $employee = Employee::create(['first_name' => 'Test', 'last_name' => 'Service', 'hourly_rate' => '15.00']);
        $day = $this->open();
        $id = $day['day']['id'];
        $body = $this->handover($day, [
            'workers' => [['employee_id' => $employee->id, 'hours' => '5', 'hourly_rate' => '15.00', 'amount' => '75.00', 'paid_cash' => true]],
            'movements' => [['kind' => 'purchase', 'amount' => '25.00', 'description' => 'Einkauf', 'receipt' => 'B-1']],
            'counted' => '600.00', 'envelope' => '350.00',
        ]);
        $handover = $this->postJson("/api/cash/days/$id/handovers", $body)->assertOk()->json();
        $this->assertSame(25000, $handover['handovers'][0]['snapshot']['calculation']['remaining_cents']);
        $this->assertSame(0, $handover['handovers'][0]['snapshot']['calculation']['difference_cents']);
        // Identical request remains a no-op even after its original revision became stale.
        $this->postJson("/api/cash/days/$id/handovers", $body)->assertOk();
        $this->assertDatabaseCount('cash_handovers', 1);
        $this->assertDatabaseCount('time_entries', 0);
        // The remaining daily cash contribution is 1393.85 - 450 = 943.85.
        $closing = $this->closing($handover, ['counted' => '1193.85', 'envelope' => '943.85']);
        $saved = $this->putJson("/api/cash/days/$id", $closing)->assertOk()->json();
        $final = $this->postJson("/api/cash/days/$id/finalize", ['revision' => $saved['day']['revision']])->assertOk()->json();
        $this->assertSame(0, $final['day']['snapshot']['closing']['difference_cents']);
        $this->assertSame(25000, $final['day']['snapshot']['closing']['remaining_cents']);
        $this->assertDatabaseCount('cash_deliveries', 1);
        $this->postJson("/api/cash/days/$id/finalize", ['revision' => $final['day']['revision']])->assertUnprocessable();
        $this->putJson("/api/cash/days/$id", $closing)->assertUnprocessable();
        $this->get("/api/cash/days/$id/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        $next = $this->open('2026-10-17', '999.00');
        $this->assertSame(25000, $next['day']['opening_cents']);
    }

    public function test_insufficient_float_and_negative_operator_cash_due_are_supported(): void
    {
        $day = $this->open();
        $id = $day['day']['id'];
        $reports = [['operator' => '2', 'reference' => 'Differenz X2-X1', 'cash_sales' => '-3.00', 'cash_due' => '-7.60', 'card_sales' => '90.30', 'card_due' => '94.90']];
        $result = $this->postJson("/api/cash/days/$id/handovers", $this->handover($day, [
            'reports' => $reports, 'movements' => [['kind' => 'purchase', 'amount' => '62.40', 'description' => 'Einkauf']], 'counted' => '180.00', 'envelope' => '0.00',
        ]))->assertOk()->json();
        $calc = $result['handovers'][0]['snapshot']['calculation'];
        $this->assertSame(18000, $calc['remaining_cents']);
        $this->assertSame(7000, $calc['top_up_cents']);
        $this->assertSame(0, $calc['difference_cents']);
    }

    public function test_z294_voucher_sale_reconciles_handover_day_pdf_and_export_without_double_counting(): void
    {
        $day = $this->open('2026-10-19');
        $id = $day['day']['id'];
        $body = $this->handover($day, [
            'reports' => [['operator' => '1 Frühschicht', 'reference' => 'Z-294 Bediener 1',
                'cash_sales' => '449.95', 'cash_due' => '432.85', 'card_sales' => '818.40', 'card_due' => '875.50', 'voucher_sales' => '40.00']],
            'counted' => '682.85', 'envelope' => '432.85',
        ]);
        $detail = $this->postJson("/api/cash/days/$id/handovers", $body)->assertOk()
            ->assertJsonPath('handovers.0.snapshot.reports.0.voucher_sales_cents', 4000)
            ->assertJsonPath('handovers.0.snapshot.reports.0.cash_sales_cents', 44995)
            ->assertJsonPath('handovers.0.snapshot.calculation.expected_cents', 68285)
            ->assertJsonPath('handovers.0.snapshot.calculation.difference_cents', 0)
            ->assertJsonPath('handovers.0.snapshot.calculation.remaining_cents', 25000)->json();
        $this->postJson("/api/cash/days/$id/handovers", $body)->assertOk();
        $this->assertDatabaseCount('cash_handovers', 1);
        $handover = DB::table('cash_handovers')->first();
        $handoverHtml = view('cash.handover', ['day' => (object) $detail['day'], 'handover' => $handover, 'snapshot' => $detail['handovers'][0]['snapshot']])->render();
        $this->assertStringContainsString('Gutscheinverkauf: 40,00 EUR', strip_tags($handoverHtml));

        $closing = $this->closing($detail, ['counted' => '478.90', 'envelope' => '228.90']);
        $closing['z'] = array_replace($closing['z'], [
            'number' => '294', 'reference' => 'Z-294.pdf', 'period_start' => '2026-10-18T21:38', 'period_end' => '2026-10-19T21:55',
            'net7' => '1672.55', 'tax7' => '117.10', 'gross7' => '1789.65',
            'net19' => '766.90', 'tax19' => '145.70', 'gross19' => '912.60',
            'cash_sales' => '756.75', 'cash_due' => '661.75', 'card_sales' => '1945.50', 'card_due' => '2080.50', 'voucher_sales' => '40.00',
        ]);
        $saved = $this->putJson("/api/cash/days/$id", $closing)->assertOk()->assertJsonPath('day.z_report.voucher_sales_cents', 4000)->json();
        $this->getJson("/api/cash/days/$id")->assertOk()->assertJsonPath('day.z_report.voucher_sales_cents', 4000);
        $final = $this->postJson("/api/cash/days/$id/finalize", ['revision' => $saved['day']['revision']])->assertOk()
            ->assertJsonPath('day.snapshot.z_report.voucher_sales_cents', 4000)
            ->assertJsonPath('day.snapshot.z_report.gross7_cents', 178965)
            ->assertJsonPath('day.snapshot.z_report.gross19_cents', 91260)
            ->assertJsonPath('day.snapshot.closing.cash_due_cents', 22890)
            ->assertJsonPath('day.snapshot.closing.expected_cents', 47890)
            ->assertJsonPath('day.snapshot.closing.difference_cents', 0)
            ->assertJsonPath('day.snapshot.closing.remaining_cents', 25000)->json();
        $payload = json_decode(DB::table('cash_deliveries')->value('payload'), true);
        $this->assertSame(4000, $payload['z_report']['voucher_sales_cents']);
        $this->assertSame(4000, $payload['handovers'][0]['snapshot']['reports'][0]['voucher_sales_cents']);
        $dayHtml = view('cash.day', ['snapshot' => $final['day']['snapshot']])->render();
        $this->assertStringContainsString('Gutscheinverkauf (Mehrzweck)', $dayHtml);
        $this->assertStringContainsString('Gutscheinverkauf: 40,00 EUR', $dayHtml);
        $this->assertStringStartsWith('%PDF-', $this->get("/api/cash/days/$id/pdf")->assertOk()->getContent());

        // Historical snapshots without the additive field must still render.
        $old = $final['day']['snapshot'];
        unset($old['z_report']['voucher_sales_cents'], $old['handovers'][0]['snapshot']['reports'][0]['voucher_sales_cents']);
        $this->assertStringContainsString('Gutscheinverkauf: 0,00 EUR', view('cash.day', ['snapshot' => $old])->render());
    }

    public function test_card_paid_voucher_sale_does_not_increase_expected_cash(): void
    {
        $day = $this->open();
        $this->postJson('/api/cash/days/'.$day['day']['id'].'/handovers', $this->handover($day, [
            'reports' => [['operator' => '1', 'reference' => 'Gutschein mit Karte bezahlt',
                'cash_sales' => '100.00', 'cash_due' => '100.00', 'card_sales' => '50.00', 'card_due' => '90.00', 'voucher_sales' => '40.00']],
            'counted' => '350.00', 'envelope' => '100.00',
        ]))->assertOk()->assertJsonPath('handovers.0.snapshot.calculation.expected_cents', 35000)
            ->assertJsonPath('handovers.0.snapshot.calculation.difference_cents', 0);
    }

    public function test_voucher_amounts_cannot_hide_mismatches_or_change_taxed_sales(): void
    {
        $day = $this->open();
        $id = $day['day']['id'];
        foreach ([null, '-40.00', '39.00', '40.001'] as $voucher) {
            $body = $this->handover($day);
            $body['reports'][0] = array_replace($body['reports'][0], [
                'cash_sales' => '449.95', 'cash_due' => '432.85', 'card_sales' => '818.40', 'card_due' => '875.50', 'voucher_sales' => $voucher,
            ]);
            $this->postJson("/api/cash/days/$id/handovers", $body)->assertUnprocessable();
        }
        $this->assertDatabaseCount('cash_handovers', 0);
        foreach (['-1.00', '40.00', '0.001'] as $voucher) {
            $body = $this->closing($day);
            $body['z']['voucher_sales'] = $voucher;
            $this->putJson("/api/cash/days/$id", $body)->assertUnprocessable();
        }
        $body = $this->closing($day);
        $body['z']['voucher_sales'] = '40.00';
        $body['z']['gross7'] = '2794.20';
        $body['z']['net7'] = '2614.00';
        $body['z']['cash_due'] = '1433.85';
        $this->putJson("/api/cash/days/$id", $body)->assertUnprocessable()->assertJsonValidationErrors('z');
        $this->assertNull(DB::table('cash_days')->value('z_report'));
    }

    public function test_omitted_or_blank_voucher_sales_default_to_zero(): void
    {
        $day = $this->open();
        $id = $day['day']['id'];
        $body = $this->handover($day);
        $body['reports'][0]['voucher_sales'] = '';
        $detail = $this->postJson("/api/cash/days/$id/handovers", $body)->assertOk()
            ->assertJsonPath('handovers.0.snapshot.reports.0.voucher_sales_cents', 0)->json();
        $this->putJson("/api/cash/days/$id", $this->closing($detail))->assertOk()->assertJsonPath('day.z_report.voucher_sales_cents', 0);
    }

    public function test_unpaid_workers_do_not_reduce_cash_and_deposits_increase_it(): void
    {
        $calc = CashCalculator::calculate(18000, 0, [['amount_cents' => -7000]], [['paid_cash' => false, 'amount_cents' => 10000]], 25000, 0, 25000);
        $this->assertSame(25000, $calc['remaining_cents']);
        $this->assertSame(0, $calc['difference_cents']);
        $this->assertSame(0, $calc['paid_cents']);
        $this->assertSame(1001, CashCalculator::cents('10.01'));
        $this->assertSame(-760, CashCalculator::cents('-7,60'));
    }

    public function test_stale_revision_and_unexplained_difference_are_rejected(): void
    {
        $day = $this->open();
        $id = $day['day']['id'];
        $this->postJson("/api/cash/days/$id/handovers", $this->handover($day, ['counted' => '701.00']))->assertUnprocessable();
        $this->postJson("/api/cash/days/$id/handovers", $this->handover($day, ['revision' => 99]))->assertUnprocessable();
        $this->assertDatabaseCount('cash_handovers', 0);
        $this->postJson("/api/cash/days/$id/handovers", $this->handover($day, ['envelope' => '701.00']))->assertUnprocessable();
        $this->postJson('/api/cash/days', ['business_date' => '2026-10-17'])->assertUnprocessable();
    }

    public function test_z_totals_must_reconcile_and_report_numbers_cannot_be_reused(): void
    {
        $day = $this->open();
        $id = $day['day']['id'];
        $bad = $this->closing($day);
        $bad['z']['tax7'] = '180.21';
        $this->putJson("/api/cash/days/$id", $bad)->assertUnprocessable();
        $good = $this->closing($day, ['counted' => '1643.85', 'envelope' => '1393.85']);
        $saved = $this->putJson("/api/cash/days/$id", $good)->assertOk()->json();
        $this->postJson("/api/cash/days/$id/finalize", ['revision' => $saved['day']['revision']])->assertOk();
        $day2 = $this->open('2026-10-17');
        $id2 = $day2['day']['id'];
        $saved2 = $this->putJson("/api/cash/days/$id2", $this->closing($day2, ['counted' => '1643.85', 'envelope' => '1393.85']))->assertOk()->json();
        $this->postJson("/api/cash/days/$id2/finalize", ['revision' => $saved2['day']['revision']])->assertUnprocessable();
    }

    public function test_delivery_retries_keep_event_id_and_require_receipt(): void
    {
        config(['cash.n8n.url' => 'https://n8n.example.test/webhook/cash', 'cash.n8n.token' => 'test-token']);
        $day = $this->open();
        $id = $day['day']['id'];
        $saved = $this->putJson("/api/cash/days/$id", $this->closing($day, ['counted' => '1643.85', 'envelope' => '1393.85']))->assertOk()->json();
        $this->postJson("/api/cash/days/$id/finalize", ['revision' => $saved['day']['revision']])->assertOk();
        $delivery = DB::table('cash_deliveries')->first();
        Http::fake(['n8n.example.test/*' => Http::sequence()->push(['accepted' => false], 200)->push(['accepted' => true, 'event_id' => $delivery->event_id], 200)]);
        try {
            (new DeliverCashDay($delivery->id))->handle();
            $this->fail('Receipt should fail');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Empfangsbestätigung', $e->getMessage());
        }
        (new DeliverCashDay($delivery->id))->handle();
        (new DeliverCashDay($delivery->id))->handle();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', $delivery->event_id) && $request['event_id'] === $delivery->event_id);
        $this->assertDatabaseHas('cash_deliveries', ['status' => 'delivered', 'attempts' => 2]);
    }

    public function test_cash_endpoints_require_authentication(): void
    {
        auth()->logout();
        $this->getJson('/api/cash/days')->assertUnauthorized();
        $this->postJson('/api/cash/vectron/sync')->assertUnauthorized();
    }

    public function test_vectron_import_uses_both_headers_and_cursor_without_duplicate_invoices(): void
    {
        config(['cash.vectron' => ['base_url' => 'https://vectron.example.test/business-data-api', 'operator_id' => 'operator', 'site_id' => 'site', 'authorization_token' => 'merchant-key', 'api_token' => 'partner-token']]);
        $invoice = ['invoiceId' => 'invoice-1', 'transactionAt' => '2026-10-16T20:00:00Z', 'total' => 100.0, 'invoiceNumber' => 123];
        Http::fake(['vectron.example.test/*' => Http::sequence()->push([$invoice])->push([$invoice])->push([])]);
        $this->postJson('/api/cash/vectron/sync')->assertOk()->assertJsonPath('imported', 1);
        $this->postJson('/api/cash/vectron/sync')->assertOk();
        $this->postJson('/api/cash/vectron/sync')->assertOk()->assertJsonPath('caught_up', true);
        $this->assertDatabaseCount('vectron_transactions', 1);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Authorization-Token', 'merchant-key') && $request->hasHeader('X-API-Token', 'partner-token') && str_contains($request->url(), 'id=invoice-1'));
        $this->getJson('/api/cash/vectron/summary?start=2026-10-16T00:00&end=2026-10-17T00:00')->assertOk()->assertJsonPath('count', 1)->assertJsonPath('reported_invoice_total_cents', 10000);
    }
}
