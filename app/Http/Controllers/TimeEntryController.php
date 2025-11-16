<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\BreakRule;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TimeEntryController extends Controller
{
    /**
     * Mitarbeiter stempelt sich ein (RFID / Web / App)
     */
    public function clockIn(Request $request)
    {
        $employee = Employee::where('rfid_tag', $request->input('rfid_tag'))->firstOrFail();

        // Prüfen, ob bereits offener Eintrag existiert
        $openEntry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        if ($openEntry) {
            return response()->json([
                'message' => 'Bereits eingestempelt',
                'entry' => $openEntry,
            ], 409);
        }

        // Auf nächste Viertelstunde runden
        $clockInTime = $this->roundToNearestQuarter(now());

        $entry = TimeEntry::create([
            'employee_id' => $employee->id,
            'clock_in' => $clockInTime,
        ]);

        return response()->json([
            'message' => 'Einstempeln erfolgreich',
            'entry' => $entry,
        ]);
    }

    /**
     * Mitarbeiter stempelt sich aus
     */
    public function clockOut(Request $request)
    {
        $employee = Employee::where('rfid_tag', $request->input('rfid_tag'))->firstOrFail();

        $entry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        if (!$entry) {
            return response()->json([
                'message' => 'Kein offener Zeiteintrag gefunden',
            ], 404);
        }

        // Auf nächste Viertelstunde runden
        $entry->clock_out = $this->roundToNearestQuarter(now());

        $clockIn = $entry->clock_in;
        $clockOut = $entry->clock_out;

        // Wenn über Mitternacht → Tageswechsel berücksichtigen
        if ($clockOut->lt($clockIn)) {
            $clockOut->addDay();
        }

        $totalMinutes = $clockOut->diffInMinutes($clockIn);
        $hours = $totalMinutes / 60;

        // Pausenregel anwenden
        $breakMinutes = BreakRule::calculateBreakForHours($hours);

        // Berechne Netto-Arbeitszeit und Lohn
        $workMinutes = max(0, $totalMinutes - $breakMinutes);
        $workHours = $workMinutes / 60;
        
        $hourlyRate = $employee->hourly_rate ?? 0;
        $grossWage = $workHours * $hourlyRate;

        $entry->break_minutes = $breakMinutes;
        $entry->hours_worked = $workHours;
        $entry->total_hours = round($workHours, 2);
        $entry->gross_wage = round($grossWage, 2);
        $entry->save();

        //Tageszusammenfassung aktualisieren
        \App\Models\DailyTimeSummary::updateForDay(
            $employee->id,
            $clockIn->copy()->startOfDay()
        );

        //Monatszusammenfassung aktualisieren
        \App\Models\MonthlyTimeSummary::updateForMonth(
            $employee->id,
            $clockIn
        );

        // Zusammenführen von Teildiensten (gleicher Kalendertag)
        self::mergePartialShifts($employee, $clockIn);

        return response()->json([
            'message' => 'Ausstempeln erfolgreich',
            'entry' => $entry,
        ]);
    }

    /**
     * Fügt mehrere Teildienste am selben Tag zu einem logischen Arbeitstag zusammen
     */
    protected static function mergePartialShifts(Employee $employee, Carbon $day)
    {
        $startOfDay = $day->copy()->startOfDay();
        $endOfDay = $day->copy()->endOfDay();

        // alle abgeschlossenen Einträge des Tages holen
        $entries = TimeEntry::where('employee_id', $employee->id)
            ->whereBetween('clock_in', [$startOfDay, $endOfDay])
            ->whereNotNull('clock_out')
            ->orderBy('clock_in')
            ->get();

        if ($entries->count() <= 1) {
            return;
        }

        $totalMinutes = $entries->sum(function ($e) {
            return ($e->hours_worked ?? 0) * 60;
        });

        $breakMinutes = BreakRule::calculateBreakForHours($totalMinutes / 60);

        // optional: in einer Zusammenfassungstabelle speichern (z. B. daily_time_summaries)
        Log::info("Teildienste zusammengefasst für {$employee->full_name}: {$totalMinutes} Min, Pause {$breakMinutes} Min");
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
}
