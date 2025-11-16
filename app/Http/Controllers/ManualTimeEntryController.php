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
            'clock_in' => 'required|date',
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

        // Auf nächste Viertelstunde runden
        $clockInTime = $this->roundToNearestQuarter(Carbon::parse($data['clock_in']));

        $entry = TimeEntry::create([
            'employee_id' => $employee->id,
            'clock_in' => $clockInTime,
            'shift_id' => null, // Keine Schicht-Verknüpfung
            'is_manual' => true,
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
            'clock_out' => 'required|date',
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
        // Auf nächste Viertelstunde runden
        $clockOut = $this->roundToNearestQuarter(Carbon::parse($data['clock_out']));
        
        // Stelle sicher, dass clock_out nach clock_in liegt
        if ($clockOut->lt($clockIn)) {
            return response()->json([
                'error' => 'Ausstempelzeit muss nach Einstempelzeit liegen'
            ], 422);
        }
        
        // Berechne Gesamtminuten
        $totalMinutes = $clockOut->diffInMinutes($clockIn);
        
        // Pausenzeit (automatisch oder manuell)
        $breakMinutes = isset($data['break_minutes']) ? (float)$data['break_minutes'] : $this->calculateAutoBreak($totalMinutes);
        
        // Stelle sicher, dass Pause nicht größer als Gesamtzeit ist
        if ($breakMinutes >= $totalMinutes) {
            $breakMinutes = 0;
        }
        
        // Netto-Arbeitsminuten
        $workMinutes = max(0, $totalMinutes - $breakMinutes);
        $workHours = $workMinutes / 60;
        
        // Lohnberechnung
        $hourlyRate = $employee->hourly_rate ?? 0;
        $grossWage = $workHours * $hourlyRate;

        // Explizit alle Felder setzen und speichern
        $entry->clock_out = $clockOut;
        $entry->break_minutes = $breakMinutes;
        $entry->total_hours = round($workHours, 2);
        $entry->gross_wage = round($grossWage, 2);
        $entry->hours_worked = round($workHours, 2);
        $entry->shift_id = null;
        $entry->save();

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

    /**
     * Rundet eine Zeit auf die nächste Viertelstunde
     */
    private function roundToNearestQuarter(Carbon $time)
    {
        $minutes = $time->minute;
        $roundedMinutes = round($minutes / 15) * 15;
        
        // Wenn 60, dann zur nächsten Stunde
        if ($roundedMinutes == 60) {
            return $time->copy()->addHour()->minute(0)->second(0);
        }
        
        return $time->copy()->minute($roundedMinutes)->second(0);
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