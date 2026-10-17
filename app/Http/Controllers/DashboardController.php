<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $now = now();
        $today = $now->toDateString();
        $open = TimeEntry::with('employee')->whereNull('clock_out')->where('clock_in', '<=', $now)->orderBy('clock_in')->get();
        $entriesToday = TimeEntry::whereDate('clock_in', $today)->whereNotNull('clock_out')->get();
        $shifts = Shift::with(['employee', 'department', 'shiftType'])->whereDate('shift_date', $today)->orderBy('start_time')->get();
        $pending = DB::table('cash_days')->where('status', 'draft')->orderBy('business_date')->first();
        $cashState = DB::table('cash_register_states')->find(1);
        $failedDeliveries = DB::table('cash_deliveries')->where('status', 'failed')->count();
        $alerts = [];
        $unassigned = $shifts->whereNull('employee_id')->count();
        if ($unassigned) {
            $alerts[] = ['title' => 'Dienstplan ergänzen', 'message' => $unassigned.' Schicht(en) heute noch ohne Mitarbeiter.', 'href' => route('timetracking.planner', absolute: false)];
        }
        if ($pending) {
            $alerts[] = ['title' => 'Tagesabschluss offen', 'message' => 'Geschäftstag '.$pending->business_date.' ist noch nicht abgeschlossen.', 'href' => route('cash.days', absolute: false)];
        }
        if ($failedDeliveries) {
            $alerts[] = ['title' => 'Übertragung prüfen', 'message' => $failedDeliveries.' n8n-Übertragung(en) mit Fehlerstatus.', 'href' => route('cash.days', absolute: false)];
        }
        $stale = $open->filter(fn ($entry) => $entry->clock_in->lt($now->copy()->subHours(18)))->count();
        if ($stale) {
            $alerts[] = ['title' => 'Offene Zeiterfassung prüfen', 'message' => $stale.' Eintrag/Einträge seit mehr als 18 Stunden offen.', 'href' => route('timetracking.manual', absolute: false)];
        }

        $activities = TimeEntry::with('employee')->orderByDesc('updated_at')->limit(8)->get()->map(fn ($entry) => [
            'id' => 'time-'.$entry->id, 'name' => $entry->employee?->full_name ?? 'Gelöschter Mitarbeiter',
            'action' => $entry->clock_out ? 'Zeiteintrag abgeschlossen' : 'Eingestempelt',
            'at' => $entry->updated_at->toIso8601String(), 'href' => route('timetracking.daily', absolute: false),
        ]);
        $cashActivities = DB::table('cash_handovers')->orderByDesc('created_at')->limit(5)->get()->map(function ($row) {
            $snapshot = json_decode($row->snapshot, true);

            return ['id' => 'handover-'.$row->id, 'name' => $snapshot['label'], 'action' => 'Schichtübergabe bestätigt',
                'at' => Carbon::parse($row->created_at)->toIso8601String(), 'href' => route('cash.handovers', absolute: false)];
        });
        $activities = $activities->concat($cashActivities)->sortByDesc('at')->take(6)->values();

        return response()->json([
            'updated_at' => $now->toIso8601String(), 'date' => $today,
            'stats' => [
                'clocked_in' => $open->pluck('employee_id')->unique()->count(),
                'active_employees' => Employee::where('active', true)->count(),
                'completed_hours_today' => round($entriesToday->sum('total_hours'), 2),
                'shifts_today' => $shifts->count(), 'unassigned_shifts_today' => $unassigned,
                'open_cash_days' => DB::table('cash_days')->where('status', 'draft')->count(),
            ],
            'attendance' => $open->map(fn ($entry) => [
                'id' => $entry->id, 'employee_id' => $entry->employee_id,
                'name' => $entry->employee?->full_name ?? 'Gelöschter Mitarbeiter',
                'clock_in' => $entry->clock_in->toIso8601String(),
                'elapsed_minutes' => max(0, (int) round(($now->timestamp - $entry->clock_in->timestamp) / 60)),
            ]),
            'shifts' => $shifts->map(fn ($shift) => [
                'id' => $shift->id, 'name' => $shift->employee?->full_name,
                'department' => $shift->department?->name, 'type' => $shift->shiftType?->name,
                'start' => substr($shift->start_time, 0, 5), 'end' => substr($shift->end_time, 0, 5),
            ]),
            'cash' => ['balance_cents' => $cashState?->balance_cents, 'last_business_date' => $cashState?->last_business_date,
                'open_business_date' => $pending?->business_date, 'updated_at' => $cashState?->updated_at],
            'alerts' => $alerts, 'activities' => $activities,
        ]);
    }
}
