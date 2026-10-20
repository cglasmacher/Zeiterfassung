<?php

namespace App\Services\Cash;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherLedger
{
    public static function numberKey(string $number): string
    {
        return ltrim($number, '0') ?: '0';
    }

    public function pendingRedemptions(int $dayId): array
    {
        return DB::table('cash_voucher_redemptions as r')->join('cash_vouchers as v', 'v.id', '=', 'r.voucher_id')
            ->where('r.cash_day_id', $dayId)->whereNull('r.handover_id')->orderBy('r.id')
            ->get(['r.id', 'r.voucher_id', 'v.number', 'r.amount_cents', 'r.created_by', 'r.created_at'])->map(function ($row) {
                $data = (array) $row;
                $data['created_at'] = Carbon::parse($row->created_at, config('app.timezone'))->toIso8601String();

                return $data;
            })->all();
    }

    public function salesForDay(int $dayId): array
    {
        return DB::table('cash_vouchers as v')->join('cash_voucher_batches as b', 'b.id', '=', 'v.batch_id')
            ->where('b.cash_day_id', $dayId)->orderBy('v.id')
            ->get(['v.id', 'v.number', 'v.sold_on', 'v.amount_cents', 'v.payment_method', 'v.created_by', 'b.handover_id'])
            ->map(fn ($row) => (array) $row)->all();
    }

    // Call only while holding the shared register-state lock, inside the accounting transaction.
    public function recordHandoverSales(int $dayId, int $handoverId, array $reports): void
    {
        foreach ($reports as $index => $report) {
            if (($report['voucher_sales_cents'] ?? 0) > 0) {
                $this->syncBatch($dayId, $handoverId, 'handover:'.$handoverId.':'.$index,
                    'SU-'.$handoverId.' · Bediener '.$report['operator'], $report['voucher_sales_cents']);
            }
        }
        $this->syncDailySales($dayId);
    }

    public function syncDailySales(int $dayId): void
    {
        $day = DB::table('cash_days')->find($dayId);
        if (! $day->z_report) {
            return;
        }
        $z = json_decode($day->z_report, true);
        $handoverSales = DB::table('cash_handovers')->where('cash_day_id', $dayId)->get(['snapshot'])->sum(function ($row) {
            return array_sum(array_column(json_decode($row->snapshot, true)['reports'], 'voucher_sales_cents'));
        });
        $remaining = ($z['voucher_sales_cents'] ?? 0) - $handoverSales;
        if ($remaining < 0) {
            throw ValidationException::withMessages(['z' => 'Die Tages-Gutscheinverkäufe dürfen nicht kleiner als die bereits bestätigten Schichtverkäufe sein.']);
        }
        $this->syncBatch($dayId, null, 'day:'.$dayId, 'Tagesabschluss · zusätzliche Gutscheinverkäufe', $remaining);
    }

    private function syncBatch(int $dayId, ?int $handoverId, string $key, string $label, int $amount): void
    {
        $existing = DB::table('cash_voucher_batches')->where('source_key', $key)->first();
        if ($existing?->registered_at && $existing->amount_cents !== $amount) {
            throw ValidationException::withMessages(['vouchers' => 'Die Gutscheinverkäufe wurden bereits nummeriert und dürfen nicht verschoben oder verändert werden.']);
        }
        if ($existing) {
            DB::table('cash_voucher_batches')->where('id', $existing->id)->update(['amount_cents' => $amount, 'updated_at' => now()]);
        } elseif ($amount > 0) {
            DB::table('cash_voucher_batches')->insert([
                'cash_day_id' => $dayId, 'handover_id' => $handoverId, 'source_key' => $key, 'label' => $label,
                'amount_cents' => $amount, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function requireRegisteredSales(int $dayId): void
    {
        if (DB::table('cash_voucher_batches')->where('cash_day_id', $dayId)->where('amount_cents', '>', 0)->whereNull('registered_at')->exists()) {
            throw ValidationException::withMessages(['vouchers' => 'Bitte unter Gutscheinverkauf zuerst alle offenen Verkäufe mit Gutscheinnummer, Betrag und Zahlart ergänzen.']);
        }
    }
}
