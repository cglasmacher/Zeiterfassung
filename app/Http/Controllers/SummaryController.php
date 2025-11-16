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

        // Berechne direkt aus TimeEntries
        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        // Hole alle abgeschlossenen Einträge für den Monat
        $entries = \DB::table('time_entries')
            ->where('employee_id', $employeeId)
            ->whereBetween('clock_in', [$startOfMonth, $endOfMonth])
            ->whereNotNull('clock_out')
            ->get();

        $totalHours = 0;
        $totalBreakMinutes = 0;
        $totalGrossWage = 0;
        $workingDays = [];

        foreach ($entries as $entry) {
            $totalHours += (float)($entry->total_hours ?? 0);
            $totalBreakMinutes += (float)($entry->break_minutes ?? 0);
            $totalGrossWage += (float)($entry->gross_wage ?? 0);
            
            // Zähle eindeutige Arbeitstage
            $day = Carbon::parse($entry->clock_in)->format('Y-m-d');
            if (!in_array($day, $workingDays)) {
                $workingDays[] = $day;
            }
        }

        return response()->json([
            'employee' => $employee->full_name,
            'month_label' => Carbon::create($year, $month, 1)->locale('de')->isoFormat('MMMM YYYY'),
            'total_hours' => round($totalHours, 2),
            'total_break_minutes' => round($totalBreakMinutes, 0),
            'total_gross_wage' => round($totalGrossWage, 2),
            'working_days' => count($workingDays),
            'entries_count' => count($entries),
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
