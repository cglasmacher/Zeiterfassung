<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{TimeEntry, Employee};
use Carbon\Carbon;

class ManualTimeEntryController extends Controller
{
    public function clockIn(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'clock_in' => 'required|date_format:Y-m-d H:i:s',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        // Prüfe ob bereits eine offene Schicht existiert
        $openEntry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->first();

        if ($openEntry) {
            return response()->json([
                'error' => 'Mitarbeiter hat bereits eine offene Schicht'
            ], 422);
        }

        $entry = TimeEntry::create([
            'employee_id' => $employee->id,
            'clock_in' => $data['clock_in'],
        ]);

        return response()->json([
            'entry' => $entry->load('employee'),
            'message' => 'Erfolgreich eingestempelt'
        ]);
    }

    public function clockOut(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'clock_out' => 'required|date_format:Y-m-d H:i:s',
            'break_minutes' => 'nullable|numeric|min:0',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        $entry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->first();

        if (!$entry) {
            return response()->json([
                'error' => 'Keine offene Schicht gefunden'
            ], 422);
        }

        $clockIn = Carbon::parse($entry->clock_in);
        $clockOut = Carbon::parse($data['clock_out']);
        
        // Berechne Gesamtminuten
        $totalMinutes = $clockOut->diffInMinutes($clockIn);
        
        // Pausenzeit (automatisch oder manuell)
        $breakMinutes = $data['break_minutes'] ?? $this->calculateAutoBreak($totalMinutes);
        
        // Netto-Arbeitsminuten
        $workMinutes = $totalMinutes - $breakMinutes;
        $workHours = $workMinutes / 60;
        
        // Lohnberechnung
        $hourlyRate = $employee->hourly_rate ?? 0;
        $grossWage = $workHours * $hourlyRate;

        $entry->update([
            'clock_out' => $data['clock_out'],
            'break_minutes' => $breakMinutes,
            'total_hours' => round($workHours, 2),
            'gross_wage' => round($grossWage, 2),
        ]);

        return response()->json([
            'entry' => $entry->load('employee'),
            'calculation' => [
                'total_minutes' => $totalMinutes,
                'break_minutes' => $breakMinutes,
                'work_minutes' => $workMinutes,
                'work_hours' => round($workHours, 2),
                'hourly_rate' => $hourlyRate,
                'gross_wage' => round($grossWage, 2),
                'employment_type' => $employee->employment_type,
            ],
            'message' => 'Erfolgreich ausgestempelt'
        ]);
    }

    /**
     * Berechne automatische Pausenzeit basierend auf gesetzlichen Vorgaben
     */
    private function calculateAutoBreak($totalMinutes)
    {
        $totalHours = $totalMinutes / 60;
        
        // Gesetzliche Pausenregelung (Deutschland)
        if ($totalHours > 9) {
            return 45; // 45 Min bei über 9 Stunden
        } elseif ($totalHours > 6) {
            return 30; // 30 Min bei über 6 Stunden
        }
        
        return 0; // Keine Pause bei unter 6 Stunden
    }

    public function getOpenEntries()
    {
        $entries = TimeEntry::with('employee')
            ->whereNull('clock_out')
            ->orderBy('clock_in', 'desc')
            ->get();

        return response()->json($entries);
    }

    public function getTodayEntries()
    {
        $today = Carbon::today();
        
        $entries = TimeEntry::with('employee')
            ->whereDate('clock_in', $today)
            ->orderBy('clock_in', 'desc')
            ->get();

        return response()->json($entries);
    }
}