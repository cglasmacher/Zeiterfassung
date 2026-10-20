<?php

namespace App\Http\Controllers;

use App\Services\Cash\CashCalculator as Money;
use App\Services\Cash\VoucherLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashVoucherController extends Controller
{
    private function present($voucher): array
    {
        $value = (array) $voucher;
        unset($value['number_key']);
        $value['status'] = $voucher->balance_cents === 0 ? 'redeemed' : ($voucher->balance_cents < $voucher->amount_cents ? 'partial' : 'open');
        $value['created_at'] = \Carbon\Carbon::parse($voucher->created_at, config('app.timezone'))->toIso8601String();

        return $value;
    }

    public function index(Request $request, VoucherLedger $ledger)
    {
        $data = $request->validate(['number' => 'nullable|regex:/^[0-9]{1,32}$/D', 'status' => 'nullable|in:open,partial,redeemed']);
        $query = DB::table('cash_vouchers');
        if (isset($data['number']) && $data['number'] !== '') {
            $query->where('number_key', VoucherLedger::numberKey($data['number']));
        }
        if (($data['status'] ?? '') === 'redeemed') {
            $query->where('balance_cents', 0);
        } elseif (($data['status'] ?? '') === 'partial') {
            $query->where('balance_cents', '>', 0)->whereColumn('balance_cents', '<', 'amount_cents');
        } elseif (($data['status'] ?? '') === 'open') {
            $query->whereColumn('balance_cents', 'amount_cents');
        }
        $vouchers = $query->orderByDesc('id')->paginate(20)->through(fn ($v) => $this->present($v));
        $day = DB::table('cash_days')->where('status', 'draft')->first(['id', 'business_date']);
        $batches = DB::table('cash_voucher_batches as b')->join('cash_days as d', 'd.id', '=', 'b.cash_day_id')
            ->whereNull('b.registered_at')->where('b.amount_cents', '>', 0)->orderBy('b.id')
            ->get(['b.id', 'b.label', 'b.amount_cents', 'd.business_date']);

        return response()->json(['vouchers' => $vouchers, 'pending_sales' => $batches, 'day' => $day,
            'pending_redemptions' => $day ? $ledger->pendingRedemptions($day->id) : []]);
    }

    public function show(int $id)
    {
        $voucher = DB::table('cash_vouchers')->find($id);
        abort_unless($voucher, 404);
        $history = DB::table('cash_voucher_redemptions as r')->join('cash_days as d', 'd.id', '=', 'r.cash_day_id')
            ->where('r.voucher_id', $id)->orderBy('r.id')->get(['r.id', 'r.amount_cents', 'r.created_at', 'r.created_by', 'r.handover_id', 'd.business_date'])
            ->map(function ($row) {
                $row->created_at = \Carbon\Carbon::parse($row->created_at, config('app.timezone'))->toIso8601String();

                return $row;
            });

        return response()->json(['voucher' => $this->present($voucher), 'redemptions' => $history]);
    }

    public function register(Request $request, int $id)
    {
        $data = $request->validate([
            'request_id' => 'required|uuid', 'vouchers' => 'required|array|min:1|max:200',
            'vouchers.*.number' => 'required|string|regex:/^[0-9]{1,32}$/D',
            'vouchers.*.amount' => 'required|numeric|gt:0', 'vouchers.*.payment_method' => 'required|in:cash,card',
        ]);
        $fingerprint = hash('sha256', json_encode(array_map(fn ($entry) => [$entry['number'], Money::cents($entry['amount']), $entry['payment_method']], $data['vouchers'])));
        DB::transaction(function () use ($data, $id, $request, $fingerprint) {
            DB::table('cash_register_states')->lockForUpdate()->find(1);
            $batch = DB::table('cash_voucher_batches')->lockForUpdate()->find($id);
            abort_unless($batch, 404);
            if ($batch->registration_request_id === $data['request_id']) {
                abort_unless($batch->registration_fingerprint === $fingerprint, 409, 'Diese Anfragenummer gehört zu anderen Verkaufsdaten.');

                return;
            }
            abort_if(DB::table('cash_voucher_batches')->where('registration_request_id', $data['request_id'])->exists(), 409, 'Diese Anfragenummer wurde bereits verwendet.');
            $day = DB::table('cash_days')->lockForUpdate()->find($batch->cash_day_id);
            if ($batch->registered_at || $batch->amount_cents === 0 || $day->status !== 'draft') {
                throw ValidationException::withMessages(['batch' => 'Dieser Verkauf ist bereits ergänzt oder nicht mehr offen.']);
            }
            $rows = [];
            foreach ($data['vouchers'] as $entry) {
                $key = VoucherLedger::numberKey($entry['number']);
                if (isset($rows[$key]) || DB::table('cash_vouchers')->where('number_key', $key)->exists()) {
                    throw ValidationException::withMessages(['number' => 'Gutscheinnummer '.$entry['number'].' ist bereits vergeben.']);
                }
                $amount = Money::cents($entry['amount']);
                $rows[$key] = ['batch_id' => $id, 'number' => $entry['number'], 'number_key' => $key,
                    'sold_on' => $day->business_date, 'amount_cents' => $amount, 'balance_cents' => $amount,
                    'payment_method' => $entry['payment_method'], 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()];
            }
            if (array_sum(array_column($rows, 'amount_cents')) !== $batch->amount_cents) {
                throw ValidationException::withMessages(['amount' => 'Die Einzelbeträge müssen genau dem automatisch übernommenen Gutscheinverkauf entsprechen.']);
            }
            DB::table('cash_vouchers')->insert(array_values($rows));
            DB::table('cash_voucher_batches')->where('id', $id)->update([
                'registration_request_id' => $data['request_id'], 'registered_at' => now(), 'updated_at' => now(),
                'registration_fingerprint' => $fingerprint,
            ]);
        });

        return response()->json(['registered' => true]);
    }

    public function redeem(Request $request)
    {
        $data = $request->validate(['request_id' => 'required|uuid', 'number' => 'required|string|regex:/^[0-9]{1,32}$/D', 'amount' => 'required|numeric|gt:0']);
        $amount = Money::cents($data['amount']);
        $key = VoucherLedger::numberKey($data['number']);
        $id = DB::transaction(function () use ($data, $amount, $key, $request) {
            DB::table('cash_register_states')->lockForUpdate()->find(1);
            $existing = DB::table('cash_voucher_redemptions')->where('request_id', $data['request_id'])->first();
            $voucher = DB::table('cash_vouchers')->where('number_key', $key)->lockForUpdate()->first();
            if (! $voucher) {
                throw ValidationException::withMessages(['number' => 'Diese Gutscheinnummer ist nicht erfasst.']);
            }
            if ($existing) {
                abort_unless($existing->voucher_id === $voucher->id && $existing->amount_cents === $amount, 409, 'Diese Anfragenummer wurde bereits für eine andere Einlösung verwendet.');

                return $voucher->id;
            }
            $day = DB::table('cash_days')->where('status', 'draft')->lockForUpdate()->first();
            if (! $day) {
                throw ValidationException::withMessages(['day' => 'Bitte zuerst einen Geschäftstag in der Schichtübergabe eröffnen.']);
            }
            if ($day->business_date < $voucher->sold_on) {
                throw ValidationException::withMessages(['day' => 'Einlösung darf nicht vor dem Verkaufsdatum liegen.']);
            }
            if ($amount > $voucher->balance_cents) {
                throw ValidationException::withMessages(['amount' => 'Der Einlösebetrag übersteigt das verfügbare Restguthaben.']);
            }
            DB::table('cash_vouchers')->where('id', $voucher->id)->update(['balance_cents' => $voucher->balance_cents - $amount, 'updated_at' => now()]);
            DB::table('cash_voucher_redemptions')->insert([
                'request_id' => $data['request_id'], 'voucher_id' => $voucher->id, 'cash_day_id' => $day->id,
                'amount_cents' => $amount, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            // A previously saved count must be checked again after a new redemption.
            DB::table('cash_days')->where('id', $day->id)->update(['closing' => null, 'revision' => $day->revision + 1, 'updated_at' => now()]);

            return $voucher->id;
        });

        return $this->show($id);
    }
}
