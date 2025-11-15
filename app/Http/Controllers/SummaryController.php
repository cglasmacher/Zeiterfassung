<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{
    Employee,
    DailyTimeSummary,
    MonthlyTimeSummary,
    TimeEntry
};
use Carbon\Carbon;

class SummaryController extends Controller
{
    /**
     * Zeigt die tägliche Zusammenfassung eines Mitarbeiters
     * Beispiel: /api/summary/daily/1/2025-10-19
     */
    public function daily($employeeId, $date)
    {
        $employee = Employee::findOrFail($employeeId);
        $day = Carbon::parse($date)->startOfDay();

        $summary = DailyTimeSummary::firstOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $day->toDateString()],
            ['total_hours' => 0, 'total_break_minutes' => 0, 'segments' => 0]
        );

        return response()->json([
            'employee' => $employee->full_name,
            'work_date' => $summary->work_date,
            'segments' => $summary->segments,
            'total_hours' => $summary->total_hours,
            'total_break_minutes' => $summary->total_break_minutes,
        ]);
    }

    /**
     * Zeigt die monatliche Zusammenfassung eines Mitarbeiters
     * Beispiel: /api/summary/monthly/1/2025/10
     */
    public function monthly($employeeId, $year, $month)
    {
        $employee = Employee::findOrFail($employeeId);

        $summary = MonthlyTimeSummary::firstOrCreate(
            ['employee_id' => $employee->id, 'year' => $year, 'month' => $month],
            ['total_hours' => 0, 'total_break_minutes' => 0, 'working_days' => 0]
        );

        return response()->json([
            'employee' => $employee->full_name,
            'month_label' => $summary->label,
            'total_hours' => $summary->total_hours,
            'total_break_minutes' => $summary->total_break_minutes,
            'working_days' => $summary->working_days,
        ]);
    }

    /**
     * Live-Status: Zeigt den aktuellen Arbeitstag inkl. laufendem Eintrag
     * Beispiel: /api/summary/current?rfid_tag=ABCD1234
     */
    public function current(Request $request)
    {
        $employee = Employee::where('rfid_tag', $request->input('rfid_tag'))->firstOrFail();
        $today = Carbon::today();

        $summary = DailyTimeSummary::where('employee_id', $employee->id)
            ->where('work_date', $today->toDateString())
            ->first();

        $openEntry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        // Berechne Stunden seit Einstempeln (immer positiv)
        $hoursSinceIn = null;
        if ($openEntry) {
            // Stelle sicher, dass beide Zeiten in der gleichen Zeitzone sind
            $clockIn = Carbon::parse($openEntry->clock_in)->setTimezone('Europe/Berlin');
            $now = Carbon::now('Europe/Berlin');
            // diffInMinutes ohne false Parameter gibt immer positive Werte zurück
            $minutesDiff = $clockIn->diffInMinutes($now);
            $hoursSinceIn = $minutesDiff / 60;
        }

        return response()->json([
            'employee' => $employee->full_name,
            'date' => $today->toDateString(),
            'open_entry' => $openEntry ? [
                'clock_in' => $openEntry->clock_in->format('H:i'),
                'hours_since_in' => round($hoursSinceIn, 2),
            ] : null,
            'daily_summary' => $summary ? [
                'segments' => $summary->segments ?? 0,
                'total_hours' => max(0, $summary->total_hours ?? 0),
                'total_break_minutes' => max(0, $summary->total_break_minutes ?? 0),
            ] : null,
        ]);
    }
}
