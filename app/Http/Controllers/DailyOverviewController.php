<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Models\Employee;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class DailyOverviewController extends Controller
{
    /**
     * Get all time entries for today, grouped by cash payment status
     */
    public function index(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        // Get all completed time entries for the day
        $allEntries = TimeEntry::with(['employee.departments'])
            ->whereBetween('clock_in', [$startOfDay, $endOfDay])
            ->whereNotNull('clock_out')
            ->orderBy('clock_in', 'desc')
            ->get();

        // Separate cash payment employees
        $cashPaymentEntries = $allEntries->filter(function ($entry) {
            return $entry->employee && $entry->employee->cash_payment;
        })->values();

        $regularEntries = $allEntries->filter(function ($entry) {
            return !$entry->employee || !$entry->employee->cash_payment;
        })->values();

        // Count unpaid cash entries
        $unpaidCount = $cashPaymentEntries->filter(function ($entry) {
            return is_null($entry->paid_out_at);
        })->count();

        return response()->json([
            'date' => $date,
            'all_entries' => $allEntries,
            'cash_payment_entries' => $cashPaymentEntries,
            'regular_entries' => $regularEntries,
            'unpaid_count' => $unpaidCount,
            'total_cash_amount' => $cashPaymentEntries->sum('gross_wage'),
        ]);
    }

    /**
     * Mark a time entry as paid out
     */
    public function markAsPaid($id)
    {
        $entry = TimeEntry::with('employee')->findOrFail($id);

        if (!$entry->clock_out) {
            return response()->json([
                'error' => 'Schicht muss abgeschlossen sein'
            ], 422);
        }

        $entry->paid_out_at = now();
        $entry->save();

        return response()->json([
            'message' => 'Als ausgezahlt markiert',
            'entry' => $entry,
        ]);
    }

    /**
     * Unmark a time entry as paid out
     */
    public function unmarkAsPaid($id)
    {
        $entry = TimeEntry::with('employee')->findOrFail($id);

        $entry->paid_out_at = null;
        $entry->save();

        return response()->json([
            'message' => 'Auszahlung zurückgesetzt',
            'entry' => $entry,
        ]);
    }

    /**
     * Generate shift end report PDF
     */
    public function generateShiftEndReport(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        // Get all cash payment entries for the day
        $entries = TimeEntry::with(['employee.departments'])
            ->whereBetween('clock_in', [$startOfDay, $endOfDay])
            ->whereNotNull('clock_out')
            ->whereHas('employee', function ($query) {
                $query->where('cash_payment', true);
            })
            ->orderBy('clock_in')
            ->get();

        if ($entries->isEmpty()) {
            return response()->json([
                'error' => 'Keine Barauszahlungen für diesen Tag gefunden'
            ], 404);
        }

        // Group by department
        $groupedByDepartment = $entries->groupBy(function ($entry) {
            $departments = $entry->employee->departments;
            if ($departments->isEmpty()) {
                return 'Ohne Abteilung';
            }
            return $departments->first()->name;
        });

        $totalAmount = $entries->sum('gross_wage');
        $unpaidCount = $entries->filter(fn($e) => is_null($e->paid_out_at))->count();

        // Verarbeite zusätzliche Ausgaben
        $expenses = $request->input('expenses', [
            'purchases' => [],
            'advances' => [],
            'other' => []
        ]);

        // Berechne Gesamtsumme der Ausgaben
        $totalExpenses = 0;
        foreach ($expenses['purchases'] as $purchase) {
            $totalExpenses += (float)($purchase['amount'] ?? 0);
        }
        foreach ($expenses['advances'] as $advance) {
            $totalExpenses += (float)($advance['amount'] ?? 0);
        }
        foreach ($expenses['other'] as $other) {
            $totalExpenses += (float)($other['amount'] ?? 0);
        }

        $data = [
            'date' => Carbon::parse($date)->format('d.m.Y'),
            'time' => now()->format('H:i'),
            'grouped_entries' => $groupedByDepartment,
            'total_wages' => $totalAmount,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'grand_total' => $totalAmount + $totalExpenses,
            'unpaid_count' => $unpaidCount,
        ];

        $pdf = Pdf::loadView('exports.shift-end-report', $data);

        // Reset all paid_out_at timestamps after generating report
        if ($request->input('reset_paid_status', false)) {
            TimeEntry::whereBetween('clock_in', [$startOfDay, $endOfDay])
                ->whereNotNull('paid_out_at')
                ->update(['paid_out_at' => null]);
        }

        return $pdf->download('Schichtende_' . Carbon::parse($date)->format('Y-m-d') . '.pdf');
    }

    /**
     * FORCE FIX - Berechnet und speichert einen einzelnen Eintrag
     */
    public function forceFix($id)
    {
        try {
            $entry = TimeEntry::with('employee')->findOrFail($id);
            
            if (!$entry->clock_out) {
                return response()->json(['error' => 'Eintrag noch nicht ausgestempelt']);
            }
            
            // Hole die RAW Werte aus der DB
            $rawEntry = \DB::table('time_entries')->where('id', $id)->first();
            
            // Parse DIREKT als Europe/Berlin (DB speichert bereits lokale Zeit!)
            $clockIn = Carbon::createFromFormat('Y-m-d H:i:s', $rawEntry->clock_in, 'Europe/Berlin');
            $clockOut = Carbon::createFromFormat('Y-m-d H:i:s', $rawEntry->clock_out, 'Europe/Berlin');
            
            // DEBUG: Zeige die Timestamps
            $debug = [
                'clockIn_timestamp' => $clockIn->timestamp,
                'clockOut_timestamp' => $clockOut->timestamp,
                'diff_seconds' => $clockOut->timestamp - $clockIn->timestamp,
            ];
            
            // Prüfe ob über Mitternacht
            if ($clockOut->lt($clockIn)) {
                $clockOut = $clockOut->copy()->addDay();
            }
            
            // Berechne Minuten MANUELL
            $totalMinutes = ($clockOut->timestamp - $clockIn->timestamp) / 60;
            $breakMinutes = (float)($entry->break_minutes ?? \App\Models\BreakRule::calculateBreakForHours($totalMinutes / 60));
            $workMinutes = max(0, $totalMinutes - $breakMinutes);
            $workHours = $workMinutes / 60;
            $hourlyRate = (float)($entry->override_hourly_rate ?? $entry->employee->hourly_rate ?? 0);
            $grossWage = $workHours * $hourlyRate;
            
            // RAW SQL UPDATE
            $affected = \DB::update(
                'UPDATE time_entries SET break_minutes = ?, total_hours = ?, gross_wage = ?, hours_worked = ?, updated_at = ? WHERE id = ?',
                [$breakMinutes, round($workHours, 2), round($grossWage, 2), round($workHours, 2), now(), $id]
            );
            
            // Prüfe ob Update erfolgreich
            $updated = \DB::table('time_entries')->where('id', $id)->first();
            
            return response()->json([
                'success' => true,
                'affected_rows' => $affected,
                'entry_id' => $id,
                'employee' => $entry->employee->full_name,
                'raw_times' => [
                    'clock_in_raw' => $rawEntry->clock_in,
                    'clock_out_raw' => $rawEntry->clock_out,
                    'clock_in_berlin' => $clockIn->toDateTimeString(),
                    'clock_out_berlin' => $clockOut->toDateTimeString(),
                ],
                'debug' => $debug,
                'calculated' => [
                    'total_minutes' => $totalMinutes,
                    'break_minutes' => $breakMinutes,
                    'work_hours' => round($workHours, 2),
                    'hourly_rate' => $hourlyRate,
                    'gross_wage' => round($grossWage, 2),
                ],
                'stored_after_update' => [
                    'break_minutes' => $updated->break_minutes,
                    'total_hours' => $updated->total_hours,
                    'gross_wage' => $updated->gross_wage,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Debug endpoint to check employee and entry data
     */
    public function debugEntry($id)
    {
        $entry = TimeEntry::with('employee')->findOrFail($id);
        
        $clockIn = $entry->clock_in;
        $clockOut = $entry->clock_out;
        
        if (!$clockOut) {
            return response()->json(['error' => 'Entry not clocked out yet']);
        }
        
        if ($clockOut->lt($clockIn)) {
            $clockOut = $clockOut->copy()->addDay();
        }
        
        $totalMinutes = $clockOut->diffInMinutes($clockIn);
        $breakMinutes = $entry->break_minutes ?? \App\Models\BreakRule::calculateBreakForHours($totalMinutes / 60);
        $workMinutes = max(0, $totalMinutes - $breakMinutes);
        $workHours = $workMinutes / 60;
        $hourlyRate = $entry->override_hourly_rate ?? $entry->employee->hourly_rate ?? 0;
        $grossWage = $workHours * $hourlyRate;
        
        return response()->json([
            'entry_id' => $entry->id,
            'employee' => $entry->employee->full_name,
            'employee_hourly_rate' => $entry->employee->hourly_rate,
            'clock_in' => $clockIn->toDateTimeString(),
            'clock_out' => $clockOut->toDateTimeString(),
            'total_minutes' => $totalMinutes,
            'break_minutes' => $breakMinutes,
            'work_minutes' => $workMinutes,
            'work_hours' => $workHours,
            'hourly_rate_used' => $hourlyRate,
            'calculated_gross_wage' => round($grossWage, 2),
            'stored_total_hours' => $entry->total_hours,
            'stored_gross_wage' => $entry->gross_wage,
        ]);
    }

    /**
     * Recalculate wages for entries on a specific date
     */
    public function recalculateWages(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        // Hole RAW Einträge aus der DB
        $rawEntries = \DB::table('time_entries')
            ->whereBetween('clock_in', [$startOfDay, $endOfDay])
            ->whereNotNull('clock_out')
            ->get();

        $updated = 0;
        $details = [];

        foreach ($rawEntries as $rawEntry) {
            // Lade Employee für hourly_rate
            $employee = \App\Models\Employee::find($rawEntry->employee_id);
            
            if (!$employee) continue;

            // Parse DIREKT als Europe/Berlin (DB speichert bereits lokale Zeit!)
            $clockIn = Carbon::createFromFormat('Y-m-d H:i:s', $rawEntry->clock_in, 'Europe/Berlin');
            $clockOut = Carbon::createFromFormat('Y-m-d H:i:s', $rawEntry->clock_out, 'Europe/Berlin');

            if ($clockOut->lt($clockIn)) {
                $clockOut = $clockOut->copy()->addDay();
            }

            // Berechne Minuten MANUELL
            $totalMinutes = ($clockOut->timestamp - $clockIn->timestamp) / 60;
            $breakMinutes = (float)($rawEntry->break_minutes ?? \App\Models\BreakRule::calculateBreakForHours($totalMinutes / 60));
            
            $workMinutes = max(0, $totalMinutes - $breakMinutes);
            $workHours = $workMinutes / 60;
            
            $hourlyRate = (float)($rawEntry->override_hourly_rate ?? $employee->hourly_rate ?? 0);
            $grossWage = $workHours * $hourlyRate;

            // RAW SQL UPDATE
            \DB::update(
                'UPDATE time_entries SET break_minutes = ?, total_hours = ?, gross_wage = ?, hours_worked = ?, updated_at = ? WHERE id = ?',
                [$breakMinutes, round($workHours, 2), round($grossWage, 2), round($workHours, 2), now(), $rawEntry->id]
            );

            $details[] = [
                'id' => $rawEntry->id,
                'employee' => $employee->full_name,
                'hours' => round($workHours, 2),
                'wage' => round($grossWage, 2),
            ];

            $updated++;
        }

        return response()->json([
            'message' => "Erfolgreich {$updated} Einträge neu berechnet",
            'updated_count' => $updated,
            'details' => $details,
        ]);
    }
}