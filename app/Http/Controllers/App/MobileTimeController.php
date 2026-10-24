<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MobileTimeController extends Controller
{
    public function status(Request $request)
    {
        $employee = $this->employee($request);
        $entry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        return response()->json([
            'clocked_in' => (bool) $entry,
            'entry' => $entry ? $this->serializeEntry($entry) : null,
        ]);
    }

    public function clockIn(Request $request)
    {
        $employee = $this->employee($request);
        $open = TimeEntry::where('employee_id', $employee->id)->whereNull('clock_out')->exists();
        abort_if($open, 409, 'Du bist bereits eingestempelt.');

        $shift = $employee->shifts()
            ->whereDate('shift_date', today())
            ->orderBy('start_time')
            ->first();

        $entry = TimeEntry::create([
            'employee_id' => $employee->id,
            'shift_id' => $shift?->id,
            'clock_in' => now(),
            'is_manual' => false,
        ]);

        return response()->json(['entry' => $this->serializeEntry($entry)], 201);
    }

    public function clockOut(Request $request)
    {
        $employee = $this->employee($request);
        $entry = TimeEntry::clockOut($employee);
        abort_unless($entry, 409, 'Du bist aktuell nicht eingestempelt.');

        return response()->json(['entry' => $this->serializeEntry($entry->fresh())]);
    }

    public function history(Request $request)
    {
        $employee = $this->employee($request);
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : now()->startOfMonth();
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $entries = TimeEntry::with('shift:id,shift_date,start_time,end_time')
            ->where('employee_id', $employee->id)
            ->whereBetween('clock_in', [$from, $to])
            ->orderByDesc('clock_in')
            ->get();

        $completed = $entries->filter(fn (TimeEntry $entry) => $entry->clock_out !== null);
        $totalHours = round($completed->sum(fn (TimeEntry $entry) => (float) ($entry->hours_worked ?? 0)), 2);
        $breakMinutes = (int) $completed->sum(fn (TimeEntry $entry) => (int) ($entry->break_minutes ?? 0));

        return response()->json([
            'month' => $month->format('Y-m'),
            'summary' => [
                'hours' => $totalHours,
                'break_minutes' => $breakMinutes,
                'days' => $completed->map(fn (TimeEntry $entry) => $entry->clock_in?->toDateString())->filter()->unique()->count(),
            ],
            'entries' => $entries->map(fn (TimeEntry $entry) => $this->serializeEntry($entry)),
        ]);
    }

    private function serializeEntry(TimeEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'shift_id' => $entry->shift_id,
            'clock_in' => $entry->clock_in?->toIso8601String(),
            'clock_out' => $entry->clock_out?->toIso8601String(),
            'break_minutes' => (int) ($entry->break_minutes ?? 0),
            'hours_worked' => $entry->hours_worked !== null ? round((float) $entry->hours_worked, 2) : null,
            'is_manual' => (bool) $entry->is_manual,
            'shift' => $entry->relationLoaded('shift') && $entry->shift ? [
                'id' => $entry->shift->id,
                'shift_date' => $entry->shift->shift_date,
                'start_time' => $entry->shift->start_time,
                'end_time' => $entry->shift->end_time,
            ] : null,
        ];
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $employee->active, 403, 'Kein aktiver Mitarbeiter hinterlegt.');

        return $employee;
    }
}
