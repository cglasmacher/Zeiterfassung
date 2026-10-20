<?php

namespace Tests\Feature\Cash;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashVoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function open(): array
    {
        return $this->postJson('/api/cash/days', ['business_date' => '2026-10-20', 'opening' => '250.00'])->assertOk()->json();
    }

    private function handover(array $day, string $sales = '0.00', string $voucherSales = '40.00', string $counted = '290.00', string $envelope = '40.00')
    {
        return $this->postJson('/api/cash/days/'.$day['day']['id'].'/handovers', [
            'revision' => $day['day']['revision'], 'request_id' => (string) Str::uuid(), 'label' => 'Service',
            'reports' => [['operator' => '1', 'reference' => 'Neu seit letzter Übergabe', 'cash_sales' => $sales,
                'cash_due' => number_format((float) $sales + (float) $voucherSales, 2, '.', ''),
                'card_sales' => '0.00', 'card_due' => '0.00', 'voucher_sales' => $voucherSales]],
            'workers' => [], 'movements' => [], 'counted' => $counted, 'envelope' => $envelope,
        ]);
    }

    private function registration(string $number = '0001', string $amount = '40.00', string $payment = 'cash'): array
    {
        return ['request_id' => (string) Str::uuid(), 'vouchers' => [['number' => $number, 'amount' => $amount, 'payment_method' => $payment]]];
    }

    private function issue(): array
    {
        $day = $this->handover($this->open())->assertOk()->json();
        $batch = DB::table('cash_voucher_batches')->first();
        $this->postJson('/api/cash/vouchers/batches/'.$batch->id.'/register', $this->registration())->assertOk();

        return $day;
    }

    private function current(int $id): array
    {
        return $this->getJson('/api/cash/days/'.$id)->assertOk()->json();
    }

    private function closeBody(array $day, string $counted = '250.00', string $envelope = '0.00', string $voucherSales = '40.00'): array
    {
        return ['revision' => $day['day']['revision'], 'workers' => [], 'movements' => [], 'counted' => $counted, 'envelope' => $envelope,
            'z' => ['number' => '295', 'register' => 'Kasse #1', 'reference' => 'Original',
                'period_start' => '2026-10-20T06:00', 'period_end' => '2026-10-21T06:00',
                'net7' => '0.00', 'tax7' => '0.00', 'gross7' => '0.00', 'net19' => '0.00', 'tax19' => '0.00', 'gross19' => '0.00',
                'cash_sales' => '0.00', 'cash_due' => $voucherSales, 'card_sales' => '0.00', 'card_due' => '0.00', 'voucher_sales' => $voucherSales]];
    }

    public function test_sales_are_automatically_created_and_must_be_completely_numbered(): void
    {
        $day = $this->handover($this->open())->assertOk()->assertJsonPath('pending_voucher_sales', 1)->json();
        $this->getJson('/api/cash/vouchers')->assertOk()->assertJsonPath('pending_sales.0.business_date', '2026-10-20')
            ->assertJsonPath('pending_sales.0.amount_cents', 4000)->assertJsonPath('vouchers.total', 0);
        $id = $day['day']['id'];
        $saved = $this->putJson("/api/cash/days/$id", $this->closeBody($day))->assertOk()->json();
        $this->postJson("/api/cash/days/$id/finalize", ['revision' => $saved['day']['revision']])->assertUnprocessable()->assertJsonValidationErrors('vouchers');
        $batch = DB::table('cash_voucher_batches')->first();
        $this->postJson('/api/cash/vouchers/batches/'.$batch->id.'/register', $this->registration(amount: '39.00'))->assertUnprocessable();
        $this->assertDatabaseCount('cash_vouchers', 0);
        $duplicate = $this->registration();
        $duplicate['vouchers'] = [['number' => '1', 'amount' => '20.00', 'payment_method' => 'cash'], ['number' => '0001', 'amount' => '20.00', 'payment_method' => 'card']];
        $this->postJson('/api/cash/vouchers/batches/'.$batch->id.'/register', $duplicate)->assertUnprocessable()->assertJsonValidationErrors('number');
        $good = $this->registration();
        $good['vouchers'] = [['number' => '0001', 'amount' => '15.00', 'payment_method' => 'cash'], ['number' => '2', 'amount' => '25.00', 'payment_method' => 'card']];
        $this->postJson('/api/cash/vouchers/batches/'.$batch->id.'/register', $good)->assertOk();
        $this->postJson('/api/cash/vouchers/batches/'.$batch->id.'/register', $good)->assertOk();
        $this->assertDatabaseCount('cash_vouchers', 2);
        $this->assertDatabaseHas('cash_vouchers', ['number' => '0001', 'sold_on' => '2026-10-20', 'amount_cents' => 1500, 'balance_cents' => 1500, 'payment_method' => 'cash']);
        $this->assertDatabaseHas('cash_vouchers', ['number' => '2', 'payment_method' => 'card']);
        $changed = $good;
        $changed['vouchers'][0]['payment_method'] = 'card';
        $this->postJson('/api/cash/vouchers/batches/'.$batch->id.'/register', $changed)->assertStatus(409);
        $this->getJson('/api/cash/vouchers?number=1')->assertOk()->assertJsonPath('vouchers.total', 1)->assertJsonPath('vouchers.data.0.number', '0001');
        $this->postJson("/api/cash/days/$id/finalize", ['revision' => $saved['day']['revision']])->assertOk();
    }

    public function test_partial_and_full_redemptions_are_audited_and_retry_safe(): void
    {
        $day = $this->issue();
        $id = $day['day']['id'];
        $body = ['request_id' => (string) Str::uuid(), 'number' => '1', 'amount' => '15.00'];
        $this->postJson('/api/cash/vouchers/redeem', $body)->assertOk()->assertJsonPath('voucher.balance_cents', 2500)->assertJsonPath('voucher.status', 'partial');
        $this->postJson('/api/cash/vouchers/redeem', $body)->assertOk()->assertJsonPath('voucher.balance_cents', 2500);
        $this->assertDatabaseCount('cash_voucher_redemptions', 1);
        $changed = [...$body, 'amount' => '10.00'];
        $this->postJson('/api/cash/vouchers/redeem', $changed)->assertStatus(409);
        $this->postJson('/api/cash/vouchers/redeem', ['request_id' => (string) Str::uuid(), 'number' => '1', 'amount' => '25.01'])->assertUnprocessable();
        $actor = User::factory()->create();
        $this->actingAs($actor)->postJson('/api/cash/vouchers/redeem', ['request_id' => (string) Str::uuid(), 'number' => '0001', 'amount' => '25.00'])
            ->assertOk()->assertJsonPath('voucher.status', 'redeemed')->assertJsonPath('voucher.balance_cents', 0)
            ->assertJsonPath('redemptions.1.created_by', $actor->id)->assertJsonPath('redemptions.1.business_date', '2026-10-20');
        $this->postJson('/api/cash/vouchers/redeem', ['request_id' => (string) Str::uuid(), 'number' => '1', 'amount' => '0.01'])->assertUnprocessable();
        $this->getJson('/api/cash/vouchers?status=redeemed')->assertOk()->assertJsonPath('vouchers.total', 1);
        $this->getJson('/api/cash/vouchers?status=partial')->assertOk()->assertJsonPath('vouchers.total', 0);
        $this->assertSame(4, $this->current($id)['day']['revision']);
        $this->assertDatabaseHas('cash_register_states', ['id' => 1, 'balance_cents' => 25000]);
    }

    public function test_redemptions_are_deducted_once_across_handovers_final_close_and_exports(): void
    {
        $day = $this->issue();
        $id = $day['day']['id'];
        $this->postJson('/api/cash/vouchers/redeem', ['request_id' => (string) Str::uuid(), 'number' => '1', 'amount' => '15.00'])->assertOk();
        $this->handover($day, '100.00', '0.00', '335.00', '85.00')->assertUnprocessable()->assertJsonValidationErrors('revision');
        $second = $this->handover($this->current($id), '100.00', '0.00', '335.00', '85.00')->assertOk()
            ->assertJsonPath('handovers.1.snapshot.calculation.voucher_redemptions_cents', 1500)
            ->assertJsonPath('handovers.1.snapshot.calculation.difference_cents', 0)
            ->assertJsonPath('handovers.1.snapshot.voucher_redemptions.0.number', '0001')
            ->assertJsonCount(0, 'voucher_redemptions')->json();
        $body = $this->closeBody($second, '350.00', '100.00');
        $body['z'] = array_replace($body['z'], ['net7' => '186.92', 'tax7' => '13.08', 'gross7' => '200.00', 'cash_sales' => '200.00', 'cash_due' => '240.00']);
        $saved = $this->putJson("/api/cash/days/$id", $body)->assertOk()->json();
        $this->postJson('/api/cash/vouchers/redeem', ['request_id' => (string) Str::uuid(), 'number' => '1', 'amount' => '10.00'])->assertOk();
        $current = $this->current($id);
        $this->assertNull($current['day']['closing']);
        $this->postJson("/api/cash/days/$id/finalize", ['revision' => $current['day']['revision']])->assertUnprocessable();
        $body['revision'] = $current['day']['revision'];
        $body['counted'] = '340.00';
        $body['envelope'] = '90.00';
        $saved = $this->putJson("/api/cash/days/$id", $body)->assertOk()->json();
        $final = $this->postJson("/api/cash/days/$id/finalize", ['revision' => $saved['day']['revision']])->assertOk()
            ->assertJsonPath('day.snapshot.closing.voucher_redemptions_cents', 1000)
            ->assertJsonPath('day.snapshot.closing.difference_cents', 0)
            ->assertJsonPath('day.snapshot.closing.remaining_cents', 25000)
            ->assertJsonPath('day.snapshot.supplement.voucher_redemptions.0.amount_cents', 1000)
            ->assertJsonPath('day.snapshot.voucher_sales.0.number', '0001')->json();
        $this->assertDatabaseCount('cash_voucher_batches', 1);
        $this->assertDatabaseHas('cash_vouchers', ['balance_cents' => 1500]);
        $payload = json_decode(DB::table('cash_deliveries')->value('payload'), true);
        $this->assertSame(1500, $payload['handovers'][1]['snapshot']['calculation']['voucher_redemptions_cents']);
        $this->assertSame(1000, $payload['closing']['voucher_redemptions_cents']);
        $html = view('cash.day', ['snapshot' => $final['day']['snapshot']])->render();
        $this->assertStringContainsString('Verkaufte Gutscheine', $html);
        $this->assertStringContainsString('Gutscheineinlösungen', $html);
        $this->assertStringContainsString('0001', $html);
        $this->assertStringStartsWith('%PDF-', $this->get("/api/cash/days/$id/pdf")->assertOk()->getContent());
        $secondRow = DB::table('cash_handovers')->orderByDesc('id')->first();
        $handoverHtml = view('cash.handover', ['snapshot' => $second['handovers'][1]['snapshot'], 'day' => (object) $second['day'], 'handover' => $secondRow])->render();
        $this->assertStringContainsString('Gutscheineinlösungen dieser Übergabe', $handoverHtml);
        $this->assertStringContainsString('0001', $handoverHtml);
    }

    public function test_daily_only_sales_create_one_batch_and_cannot_be_changed_after_numbering(): void
    {
        $day = $this->open();
        $id = $day['day']['id'];
        $body = $this->closeBody($day, '290.00', '40.00');
        $saved = $this->putJson("/api/cash/days/$id", $body)->assertOk()->json();
        $batch = DB::table('cash_voucher_batches')->first();
        $this->assertNull($batch->handover_id);
        $body['revision'] = $saved['day']['revision'];
        $saved = $this->putJson("/api/cash/days/$id", $body)->assertOk()->json();
        $this->assertDatabaseCount('cash_voucher_batches', 1);
        $this->postJson('/api/cash/vouchers/batches/'.$batch->id.'/register', $this->registration())->assertOk();
        $changed = $this->closeBody($saved, '300.00', '50.00', '50.00');
        $this->putJson("/api/cash/days/$id", $changed)->assertUnprocessable()->assertJsonValidationErrors('vouchers');
        $this->handover($saved)->assertUnprocessable()->assertJsonValidationErrors('vouchers');
        $this->assertDatabaseCount('cash_handovers', 0);
        $this->assertDatabaseHas('cash_register_states', ['id' => 1, 'balance_cents' => 25000]);
        $this->postJson("/api/cash/days/$id/finalize", ['revision' => $saved['day']['revision']])->assertOk();
        $this->postJson('/api/cash/vouchers/redeem', ['request_id' => (string) Str::uuid(), 'number' => '1', 'amount' => '5.00'])->assertUnprocessable()->assertJsonValidationErrors('day');
        $this->postJson('/api/cash/days', ['business_date' => '2026-10-21'])->assertOk();
        $this->postJson('/api/cash/vouchers/redeem', ['request_id' => (string) Str::uuid(), 'number' => '1', 'amount' => '5.00'])->assertOk()
            ->assertJsonPath('redemptions.0.business_date', '2026-10-21');
    }

    public function test_duplicate_numbers_unknown_codes_and_invalid_amounts_are_rejected(): void
    {
        $day = $this->issue();
        $second = $this->handover($day)->assertOk()->json();
        $batch = DB::table('cash_voucher_batches')->orderByDesc('id')->first();
        $this->postJson('/api/cash/vouchers/batches/'.$batch->id.'/register', $this->registration('1'))->assertUnprocessable()->assertJsonValidationErrors('number');
        foreach (['abc', '-1', '1.1'] as $number) {
            $this->postJson('/api/cash/vouchers/batches/'.$batch->id.'/register', $this->registration($number))->assertUnprocessable();
        }
        foreach (['0.00', '-1.00', '0.001'] as $amount) {
            $this->postJson('/api/cash/vouchers/redeem', ['request_id' => (string) Str::uuid(), 'number' => '1', 'amount' => $amount])->assertUnprocessable();
        }
        $this->postJson('/api/cash/vouchers/redeem', ['request_id' => (string) Str::uuid(), 'number' => '9999', 'amount' => '1.00'])->assertUnprocessable()->assertJsonValidationErrors('number');
        $this->assertDatabaseCount('cash_voucher_redemptions', 0);
        $this->assertDatabaseHas('cash_vouchers', ['balance_cents' => 4000]);
        $this->assertSame($second['day']['revision'], $this->current($day['day']['id'])['day']['revision']);
    }

    public function test_page_and_endpoints_require_login(): void
    {
        $this->withoutVite();
        $this->get('/kasse/gutscheinverkauf')->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page->component('TimeTracking/Cash/Vouchers'));
        auth()->logout();
        $this->get('/kasse/gutscheinverkauf')->assertRedirect('/login');
        $this->getJson('/api/cash/vouchers')->assertUnauthorized();
        $this->postJson('/api/cash/vouchers/redeem', [])->assertUnauthorized();
        $this->postJson('/api/cash/vouchers/batches/1/register', [])->assertUnauthorized();
        $this->getJson('/api/cash/vouchers/1')->assertUnauthorized();
    }

    public function test_daily_remainder_only_creates_additional_sales_and_can_be_revised_while_unassigned(): void
    {
        $day = $this->issue();
        $id = $day['day']['id'];
        $body = $this->closeBody($day, '270.00', '20.00', '60.00');
        $saved = $this->putJson("/api/cash/days/$id", $body)->assertOk()->json();
        $this->assertDatabaseCount('cash_voucher_batches', 2);
        $daily = DB::table('cash_voucher_batches')->whereNull('handover_id')->first();
        $this->assertSame(2000, $daily->amount_cents);
        $changed = $this->closeBody($saved, '260.00', '10.00', '50.00');
        $saved = $this->putJson("/api/cash/days/$id", $changed)->assertOk()->json();
        $this->assertDatabaseHas('cash_voucher_batches', ['id' => $daily->id, 'amount_cents' => 1000]);
        $this->postJson('/api/cash/vouchers/batches/'.$daily->id.'/register', $this->registration('2', '10.00', 'card'))->assertOk();
        $this->postJson("/api/cash/days/$id/finalize", ['revision' => $saved['day']['revision']])->assertOk()
            ->assertJsonPath('day.snapshot.z_report.voucher_sales_cents', 5000)->assertJsonCount(2, 'day.snapshot.voucher_sales');
        $this->assertSame(5000, (int) DB::table('cash_vouchers')->sum('amount_cents'));
    }
}
