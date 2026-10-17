<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Setting;
use App\Services\Cash\CashCalculator as Money;
use App\Services\Cash\VectronClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashAccountingController extends Controller
{
    private function decode($row): array
    {
        $data = (array) $row;
        foreach (['snapshot', 'supplement', 'z_report', 'closing', 'payload'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = json_decode($data[$key], true);
            }
        }

        return $data;
    }

    public function index(Request $request)
    {
        return response()->json([
            'days' => DB::table('cash_days')->orderByDesc('business_date')->limit(60)->get()->map(fn ($row) => $this->decode($row)),
            'employees' => Employee::where('active', true)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'hourly_rate']),
            'state' => DB::table('cash_register_states')->find(1),
            'float_target' => Setting::get('cash_float_target', '250.00'),
        ]);
    }

    public function show(int $id)
    {
        $day = DB::table('cash_days')->find($id);
        abort_unless($day, 404);

        return response()->json([
            'day' => $this->decode($day),
            'handovers' => DB::table('cash_handovers')->where('cash_day_id', $id)->orderBy('id')->get()->map(fn ($row) => $this->decode($row)),
            'delivery' => DB::table('cash_deliveries')->where('cash_day_id', $id)->first(['event_id', 'status', 'attempts', 'last_error', 'delivered_at']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['business_date' => 'required|date_format:Y-m-d', 'opening' => 'nullable|numeric|min:0|max:9999999']);
        $id = DB::transaction(function () use ($request, $data) {
            $state = DB::table('cash_register_states')->lockForUpdate()->find(1);
            if (DB::table('cash_days')->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['day' => 'Zuerst den offenen Geschäftstag abschließen.']);
            }
            if ($state->last_business_date && $data['business_date'] <= $state->last_business_date) {
                throw ValidationException::withMessages(['business_date' => 'Geschäftstage müssen chronologisch angelegt werden.']);
            }
            if ($state->balance_cents === null && ! isset($data['opening'])) {
                throw ValidationException::withMessages(['opening' => 'Beim ersten Geschäftstag den tatsächlichen Anfangsbestand eingeben.']);
            }
            $opening = $state->balance_cents ?? Money::cents($data['opening']);
            $id = DB::table('cash_days')->insertGetId([
                'business_date' => $data['business_date'], 'opening_cents' => $opening,
                'float_target_cents' => Money::cents(Setting::get('cash_float_target', '250.00')),
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('cash_register_states')->where('id', 1)->update(['balance_cents' => $opening, 'updated_at' => now()]);

            return $id;
        });

        return $this->show($id);
    }

    private function lockedDay(int $id, int $revision)
    {
        DB::table('cash_register_states')->lockForUpdate()->find(1);
        $day = DB::table('cash_days')->lockForUpdate()->find($id);
        abort_unless($day, 404);
        if ($day->status !== 'draft' || $day->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => 'Abschluss gesperrt oder zwischenzeitlich geändert. Bitte neu laden.']);
        }

        return $day;
    }

    private function entries(Request $request): array
    {
        $data = $request->validate([
            'workers' => 'present|array|max:100', 'workers.*.employee_id' => 'required|exists:employees,id',
            'workers.*.hours' => 'required|numeric|gt:0|max:24', 'workers.*.hourly_rate' => 'required|numeric|min:0|max:9999',
            'workers.*.amount' => 'required|numeric|min:0|max:999999', 'workers.*.paid_cash' => 'required|boolean',
            'workers.*.note' => 'nullable|string|max:500',
            'movements' => 'present|array|max:100', 'movements.*.kind' => 'required|in:purchase,expense,deposit,withdrawal',
            'movements.*.amount' => 'required|numeric|gt:0|max:999999',
            'movements.*.description' => 'required|string|max:500', 'movements.*.receipt' => 'nullable|string|max:255',
        ]);
        $workers = array_map(function ($worker) {
            $employee = Employee::findOrFail($worker['employee_id']);
            $minutes = (int) round((float) $worker['hours'] * 60);
            $rate = Money::cents($worker['hourly_rate']);

            return [
                'employee_id' => $employee->id, 'name' => $employee->full_name, 'minutes' => $minutes,
                'hourly_rate_cents' => $rate, 'suggested_cents' => (int) round($minutes * $rate / 60),
                'amount_cents' => Money::cents($worker['amount']), 'paid_cash' => (bool) $worker['paid_cash'],
                'note' => $worker['note'] ?? '',
            ];
        }, $data['workers']);
        $movements = array_map(fn ($movement) => [
            'kind' => $movement['kind'], 'description' => $movement['description'], 'receipt' => $movement['receipt'] ?? '',
            'amount_cents' => Money::cents($movement['amount']) * ($movement['kind'] === 'deposit' ? -1 : 1),
        ], $data['movements']);

        return compact('workers', 'movements');
    }

    public function handover(Request $request, int $id)
    {
        $data = $request->validate([
            'revision' => 'required|integer', 'request_id' => 'required|uuid', 'label' => 'required|string|max:255',
            'reports' => 'required|array|min:1|max:50', 'reports.*.operator' => 'required|string|max:100',
            'reports.*.reference' => 'required|string|max:255',
            'reports.*.cash_sales' => 'required|numeric', 'reports.*.cash_due' => 'required|numeric',
            'reports.*.card_sales' => 'required|numeric', 'reports.*.card_due' => 'required|numeric',
            'counted' => 'required|numeric|min:0', 'envelope' => 'required|numeric|min:0', 'note' => 'nullable|string|max:2000',
        ]);
        $entries = $this->entries($request);
        DB::transaction(function () use ($request, $id, $data, $entries) {
            // Serialize retries before checking their unique request ID.
            DB::table('cash_register_states')->lockForUpdate()->find(1);
            $existing = DB::table('cash_handovers')->where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless($existing->cash_day_id == $id, 409);

                return;
            }
            $day = $this->lockedDay($id, $data['revision']);
            $reports = array_map(function ($report) {
                foreach (['cash_sales', 'cash_due', 'card_sales', 'card_due'] as $key) {
                    $report[$key.'_cents'] = Money::cents($report[$key]);
                    unset($report[$key]);
                }
                if ($report['cash_sales_cents'] + $report['card_sales_cents'] !== $report['cash_due_cents'] + $report['card_due_cents']) {
                    throw ValidationException::withMessages(['reports' => 'Bar/Karte-Umsatz und Bar/Karte-Soll müssen in Summe übereinstimmen. Weitere Finanzwege sind separat zu klären.']);
                }

                return $report;
            }, $data['reports']);
            $opening = DB::table('cash_register_states')->where('id', 1)->value('balance_cents');
            $calculation = Money::calculate($opening, array_sum(array_column($reports, 'cash_due_cents')),
                $entries['movements'], $entries['workers'], Money::cents($data['counted']), Money::cents($data['envelope']), $day->float_target_cents);
            $this->requireDifferenceNote($calculation, $data['note'] ?? '');
            DB::table('cash_handovers')->insertGetId([
                'cash_day_id' => $id, 'request_id' => $data['request_id'],
                'snapshot' => json_encode([...$entries, 'label' => $data['label'], 'reports' => $reports, 'calculation' => $calculation, 'note' => $data['note'] ?? '']),
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('cash_register_states')->where('id', 1)->update(['balance_cents' => $calculation['remaining_cents'], 'updated_at' => now()]);
            DB::table('cash_days')->where('id', $id)->update(['revision' => $day->revision + 1, 'updated_at' => now()]);
        });

        return $this->show($id);
    }

    private function requireDifferenceNote(array $calculation, string $note): void
    {
        if ($calculation['difference_cents'] !== 0 && trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'Bitte die Kassendifferenz begründen.']);
        }
    }

    public function save(Request $request, int $id)
    {
        $data = $request->validate(['revision' => 'required|integer',
            'z' => 'required|array', 'z.number' => 'required|string|max:100', 'z.register' => 'required|string|max:100',
            'z.period_start' => 'required|date', 'z.period_end' => 'required|date|after:z.period_start',
            'z.net7' => 'required|numeric', 'z.tax7' => 'required|numeric', 'z.gross7' => 'required|numeric',
            'z.net19' => 'required|numeric', 'z.tax19' => 'required|numeric', 'z.gross19' => 'required|numeric',
            'z.cash_sales' => 'required|numeric', 'z.cash_due' => 'required|numeric',
            'z.card_sales' => 'required|numeric', 'z.card_due' => 'required|numeric',
            'z.reference' => 'required|string|max:255', 'z.reconciliation_note' => 'nullable|string|max:2000',
            'counted' => 'required|numeric|min:0', 'envelope' => 'required|numeric|min:0', 'note' => 'nullable|string|max:2000',
        ]);
        $entries = $this->entries($request);
        $z = $data['z'];
        foreach (['net7', 'tax7', 'gross7', 'net19', 'tax19', 'gross19', 'cash_sales', 'cash_due', 'card_sales', 'card_due'] as $key) {
            $z[$key.'_cents'] = Money::cents($z[$key]);
            unset($z[$key]);
        }
        if ($z['gross7_cents'] !== $z['net7_cents'] + $z['tax7_cents'] || $z['gross19_cents'] !== $z['net19_cents'] + $z['tax19_cents']
            || $z['gross7_cents'] + $z['gross19_cents'] !== $z['cash_sales_cents'] + $z['card_sales_cents']
            || $z['cash_sales_cents'] + $z['card_sales_cents'] !== $z['cash_due_cents'] + $z['card_due_cents']) {
            throw ValidationException::withMessages(['z' => 'Z-Bericht stimmt nicht ab: Netto + Steuer = Brutto; Steuerumsätze = Zahlartumsätze = Zahlart-Sollsummen.']);
        }
        $z['source'] = 'manual_z_report';
        $z['period_start'] = \Carbon\Carbon::parse($z['period_start'], 'Europe/Berlin')->utc()->toIso8601String();
        $z['period_end'] = \Carbon\Carbon::parse($z['period_end'], 'Europe/Berlin')->utc()->toIso8601String();
        DB::transaction(function () use ($id, $data, $entries, $z) {
            $day = $this->lockedDay($id, $data['revision']);
            DB::table('cash_days')->where('id', $id)->update([
                'supplement' => json_encode($entries), 'z_report' => json_encode($z),
                'closing' => json_encode(['counted_cents' => Money::cents($data['counted']), 'envelope_cents' => Money::cents($data['envelope']), 'note' => $data['note'] ?? '']),
                'revision' => $day->revision + 1, 'updated_at' => now(),
            ]);
        });

        return $this->show($id);
    }

    public function finalize(Request $request, int $id)
    {
        $data = $request->validate(['revision' => 'required|integer']);
        DB::transaction(function () use ($request, $id, $data) {
            $day = $this->lockedDay($id, $data['revision']);
            $z = json_decode($day->z_report ?? 'null', true);
            $closing = json_decode($day->closing ?? 'null', true);
            if (! $z || ! $closing) {
                throw ValidationException::withMessages(['day' => 'Zuerst Tagesabschluss vollständig speichern.']);
            }
            $supplement = json_decode($day->supplement, true);
            $handovers = DB::table('cash_handovers')->where('cash_day_id', $id)->orderBy('id')->get()->map(fn ($row) => $this->decode($row))->all();
            $handoverDue = array_sum(array_map(fn ($row) => $row['snapshot']['calculation']['cash_due_cents'], $handovers));
            $state = DB::table('cash_register_states')->find(1);
            $calculation = Money::calculate($state->balance_cents, $z['cash_due_cents'] - $handoverDue,
                $supplement['movements'], $supplement['workers'], $closing['counted_cents'], $closing['envelope_cents'], $day->float_target_cents);
            $this->requireDifferenceNote($calculation, $closing['note']);
            if (DB::table('cash_days')->where('status', 'finalized')->get()->contains(function ($other) use ($z) {
                $report = json_decode($other->z_report, true);

                return ($report['number'] === $z['number'] && $report['register'] === $z['register'])
                    || ($report['register'] === $z['register'] && $z['period_start'] < $report['period_end'] && $z['period_end'] > $report['period_start']);
            })) {
                throw ValidationException::withMessages(['z' => 'Z-Bericht oder Berichtszeitraum wurde für diese Kasse bereits abgeschlossen.']);
            }
            $snapshot = [
                'schema_version' => 1, 'event_type' => 'cash_day.finalized', 'event_id' => (string) Str::uuid(),
                'business_date' => $day->business_date, 'day_id' => $id, 'version' => 1, 'currency' => 'EUR',
                'opening_cents' => $day->opening_cents, 'float_target_cents' => $day->float_target_cents,
                'z_report' => $z, 'handovers' => $handovers, 'supplement' => $supplement,
                'closing' => [...$calculation, 'note' => $closing['note'], 'envelope_reference' => 'TA-'.$id],
                'finalized_by' => $request->user()->id, 'finalized_at' => now()->toIso8601String(),
            ];
            DB::table('cash_days')->where('id', $id)->update(['status' => 'finalized', 'snapshot' => json_encode($snapshot),
                'finalized_at' => now(), 'revision' => $day->revision + 1, 'updated_at' => now()]);
            DB::table('cash_register_states')->where('id', 1)->update(['balance_cents' => $calculation['remaining_cents'], 'last_business_date' => $day->business_date, 'updated_at' => now()]);
            DB::table('cash_deliveries')->insert(['cash_day_id' => $id, 'event_id' => $snapshot['event_id'], 'payload' => json_encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
        });

        return $this->show($id);
    }

    public function pdf(int $id)
    {
        $day = DB::table('cash_days')->find($id);
        abort_unless($day && $day->snapshot, 422, 'Nur abgeschlossene Tage können exportiert werden.');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('cash.day', ['snapshot' => json_decode($day->snapshot, true)])
            ->download('Tagesabschluss_'.$day->business_date.'.pdf');
    }

    public function deliver(int $id)
    {
        $delivery = DB::table('cash_deliveries')->where('cash_day_id', $id)->first();
        abort_unless($delivery, 422, 'Zuerst abschließen.');
        \App\Jobs\DeliverCashDay::dispatch($delivery->id);

        return response()->json(['message' => 'Übertragung beauftragt. Status nach Verarbeitung neu laden.']);
    }

    public function vectronSync(VectronClient $client)
    {
        return response()->json($client->sync());
    }

    public function vectronSummary(Request $request)
    {
        $data = $request->validate(['start' => 'required|date', 'end' => 'required|date|after:start']);
        $start = \Carbon\Carbon::parse($data['start'], 'Europe/Berlin')->utc();
        $end = \Carbon\Carbon::parse($data['end'], 'Europe/Berlin')->utc();
        $rows = DB::table('vectron_transactions')->where('site_id', config('cash.vectron.site_id'))
            ->where('transaction_at', '>=', $start)->where('transaction_at', '<', $end)->get();
        $gross = 0;
        $warnings = [];
        foreach ($rows as $row) {
            $invoice = json_decode($row->payload, true);
            $gross += (int) round((float) ($invoice['total'] ?? 0) * 100);
            if (! empty($invoice['discounts']) || ! empty($invoice['surcharges'])) {
                $warnings[] = 'Rechnung '.$invoice['invoiceNumber'].': Rechnungsrabatte/Zuschläge gesondert gegen Z-Bericht prüfen.';
            }
            if (($invoice['transactionType'] ?? '') === 'CANCELLED_INVOICE') {
                $warnings[] = 'Stornierte Rechnung '.$invoice['invoiceNumber'].': Vorzeichen und Referenz prüfen.';
            }
        }

        return response()->json(['count' => $rows->count(), 'reported_invoice_total_cents' => $gross,
            'warnings' => array_values(array_unique($warnings)), 'source' => 'bi_transactions_control_only',
            'message' => 'Kontrollsumme importierter Rechnungen; kein Z-Bericht und keine verbindliche Steueraufteilung.']);
    }
}
