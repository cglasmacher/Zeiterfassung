<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HaccpOperationsController extends Controller
{
    public function index()
    {
        $maintenance = DB::table('haccp_maintenance_plans as p')
            ->join('haccp_devices as d', 'd.id', '=', 'p.device_id')
            ->leftJoin('haccp_maintenance_records as r', function ($join) {
                $join->on('r.maintenance_plan_id', '=', 'p.id')
                    ->whereRaw('r.id = (select max(r2.id) from haccp_maintenance_records r2 where r2.maintenance_plan_id = p.id)');
            })
            ->select('p.*', 'd.name as device_name', 'd.location', 'r.completed_at as last_record_at', 'r.provider as last_provider')
            ->orderByRaw('p.next_due_at is null, p.next_due_at')
            ->get();

        $deviations = DB::table('haccp_deviations as d')
            ->join('haccp_task_results as r', 'r.id', '=', 'd.task_result_id')
            ->join('haccp_tasks as t', 't.id', '=', 'r.task_id')
            ->leftJoin('haccp_devices as hd', 'hd.id', '=', 't.device_id')
            ->leftJoin('users as resolver', 'resolver.id', '=', 'd.resolved_by')
            ->select('d.*', 'r.numeric_value', 'r.completed_at', 't.title', 'hd.name as device_name', 'hd.location', 'resolver.name as resolved_by_name')
            ->orderByRaw('d.resolved_at is null desc')
            ->orderByDesc('d.created_at')
            ->get();

        return response()->json(['maintenance' => $maintenance, 'deviations' => $deviations]);
    }

    public function storeMaintenancePlan(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer', 'exists:haccp_devices,id'],
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'in:days,weeks,months,years'],
            'interval_value' => ['required', 'integer', 'min:1', 'max:365'],
            'next_due_at' => ['nullable', 'date'],
        ]);

        $id = DB::table('haccp_maintenance_plans')->insertGetId($data + ['active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['id' => $id], 201);
    }

    public function completeMaintenance(Request $request, int $id)
    {
        $plan = DB::table('haccp_maintenance_plans')->where('id', $id)->first();
        abort_unless($plan, 404);
        $data = $request->validate(['provider' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000'], 'completed_at' => ['nullable', 'date']]);
        $completedAt = Carbon::parse($data['completed_at'] ?? now());
        $next = match ($plan->frequency) {
            'days' => $completedAt->copy()->addDays($plan->interval_value),
            'weeks' => $completedAt->copy()->addWeeks($plan->interval_value),
            'years' => $completedAt->copy()->addYears($plan->interval_value),
            default => $completedAt->copy()->addMonths($plan->interval_value),
        };

        DB::transaction(function () use ($id, $data, $completedAt, $next, $request) {
            DB::table('haccp_maintenance_records')->insert([
                'maintenance_plan_id' => $id, 'completed_by' => $request->user()->id,
                'completed_at' => $completedAt->toDateString(), 'provider' => $data['provider'] ?? null,
                'notes' => $data['notes'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('haccp_maintenance_plans')->where('id', $id)->update([
                'last_completed_at' => $completedAt->toDateString(), 'next_due_at' => $next->toDateString(), 'updated_at' => now(),
            ]);
        });
        return response()->json(['next_due_at' => $next->toDateString()]);
    }

    public function resolveDeviation(Request $request, int $id)
    {
        $deviation = DB::table('haccp_deviations')->where('id', $id)->first();
        abort_unless($deviation, 404);
        abort_if($deviation->resolved_at, 409, 'Diese Abweichung ist bereits abgeschlossen.');
        $data = $request->validate([
            'corrective_action' => ['required', 'string', 'max:5000'],
            'follow_up_value' => ['nullable', 'numeric'],
            'follow_up_note' => ['nullable', 'string', 'max:5000'],
        ]);
        $action = $data['corrective_action'];
        if (array_key_exists('follow_up_value', $data)) $action .= "\nNachkontrolle: {$data['follow_up_value']}";
        if (!empty($data['follow_up_note'])) $action .= "\nNachkontrolle/Vermerk: {$data['follow_up_note']}";
        DB::table('haccp_deviations')->where('id', $id)->update([
            'corrective_action' => $action, 'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['resolved' => true]);
    }
}
