<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{TimeEntry, TimeEntryAudit, Employee};
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TimeEntryManipulationController extends Controller
{
    public function getMonthEntries(Request $request)
    {
        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);
        $employeeId = $request->query('employee_id');

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $query = TimeEntry::with(['employee', 'audits.user'])
            ->whereBetween('clock_in', [$startDate, $endDate])
            ->whereNotNull('clock_out');

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $entries = $query->orderBy('clock_in', 'desc')->get();

        return response()->json([
            'entries' => $entries,
            'period' => [
                'year' => $year,
                'month' => $month,
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    public function updateEntry(Request $request, $id)
    {
        $entry = TimeEntry::with('employee')->findOrFail($id);
        
        $data = $request->validate([
            'clock_in' => 'required|date',
            'clock_out' => 'required|date|after:clock_in',
            'break_minutes' => 'required|numeric|min:0',
            'override_hourly_rate' => 'nullable|numeric|min:0',
            'admin_note' => 'nullable|string',
        ]);

        // Speichere alte Werte für Audit
        $oldValues = $entry->only(['clock_in', 'clock_out', 'break_minutes', 'total_hours', 'gross_wage', 'override_hourly_rate', 'admin_note']);

        // Berechne neue Werte
        $clockIn = Carbon::parse($data['clock_in']);
        $clockOut = Carbon::parse($data['clock_out']);
        $totalMinutes = $clockOut->diffInMinutes($clockIn);
        $workMinutes = $totalMinutes - $data['break_minutes'];
        $workHours = $workMinutes / 60;
        
        $hourlyRate = $data['override_hourly_rate'] ?? $entry->employee->hourly_rate ?? 0;
        $grossWage = $workHours * $hourlyRate;

        // Update Entry
        $entry->update([
            'clock_in' => $data['clock_in'],
            'clock_out' => $data['clock_out'],
            'break_minutes' => $data['break_minutes'],
            'total_hours' => round($workHours, 2),
            'gross_wage' => round($grossWage, 2),
            'override_hourly_rate' => $data['override_hourly_rate'],
            'admin_note' => $data['admin_note'],
        ]);

        // Audit Log
        TimeEntryAudit::create([
            'time_entry_id' => $entry->id,
            'user_id' => Auth::id(),
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $entry->only(['clock_in', 'clock_out', 'break_minutes', 'total_hours', 'gross_wage', 'override_hourly_rate', 'admin_note']),
            'note' => 'Eintrag manuell bearbeitet',
        ]);

        return response()->json([
            'entry' => $entry->load('employee', 'audits.user'),
            'message' => 'Eintrag erfolgreich aktualisiert',
        ]);
    }

    public function splitEntry(Request $request, $id)
    {
        $entry = TimeEntry::with('employee')->findOrFail($id);
        
        $data = $request->validate([
            'splits' => 'required|array|min:2',
            'splits.*.clock_in' => 'required|date',
            'splits.*.clock_out' => 'required|date|after:splits.*.clock_in',
            'splits.*.break_minutes' => 'required|numeric|min:0',
            'splits.*.admin_note' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $oldValues = $entry->only(['clock_in', 'clock_out', 'break_minutes', 'total_hours', 'gross_wage']);
            
            $newEntries = [];
            
            foreach ($data['splits'] as $index => $split) {
                $clockIn = Carbon::parse($split['clock_in']);
                $clockOut = Carbon::parse($split['clock_out']);
                $totalMinutes = $clockOut->diffInMinutes($clockIn);
                $workMinutes = $totalMinutes - $split['break_minutes'];
                $workHours = $workMinutes / 60;
                
                $hourlyRate = $entry->override_hourly_rate ?? $entry->employee->hourly_rate ?? 0;
                $grossWage = $workHours * $hourlyRate;

                if ($index === 0) {
                    // Update original entry
                    $entry->update([
                        'clock_in' => $split['clock_in'],
                        'clock_out' => $split['clock_out'],
                        'break_minutes' => $split['break_minutes'],
                        'total_hours' => round($workHours, 2),
                        'gross_wage' => round($grossWage, 2),
                        'admin_note' => $split['admin_note'] ?? null,
                    ]);
                    $newEntries[] = $entry;
                } else {
                    // Create new entries
                    $newEntry = TimeEntry::create([
                        'employee_id' => $entry->employee_id,
                        'shift_id' => $entry->shift_id,
                        'clock_in' => $split['clock_in'],
                        'clock_out' => $split['clock_out'],
                        'break_minutes' => $split['break_minutes'],
                        'total_hours' => round($workHours, 2),
                        'gross_wage' => round($grossWage, 2),
                        'override_hourly_rate' => $entry->override_hourly_rate,
                        'admin_note' => $split['admin_note'] ?? null,
                        'is_manual' => true,
                    ]);
                    $newEntries[] = $newEntry;
                }
            }

            // Audit Log
            TimeEntryAudit::create([
                'time_entry_id' => $entry->id,
                'user_id' => Auth::id(),
                'action' => 'split',
                'old_values' => $oldValues,
                'new_values' => [
                    'split_count' => count($data['splits']),
                    'new_entry_ids' => collect($newEntries)->pluck('id')->toArray(),
                ],
                'note' => 'Eintrag in ' . count($data['splits']) . ' Teile aufgeteilt',
            ]);

            DB::commit();

            return response()->json([
                'entries' => collect($newEntries)->load('employee', 'audits.user'),
                'message' => 'Eintrag erfolgreich aufgeteilt',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Fehler beim Aufteilen: ' . $e->getMessage()], 500);
        }
    }

    public function deleteEntry(Request $request, $id)
    {
        $entry = TimeEntry::findOrFail($id);
        
        $oldValues = $entry->only(['clock_in', 'clock_out', 'break_minutes', 'total_hours', 'gross_wage']);

        // Audit Log before deletion
        TimeEntryAudit::create([
            'time_entry_id' => $entry->id,
            'user_id' => Auth::id(),
            'action' => 'deleted',
            'old_values' => $oldValues,
            'new_values' => null,
            'note' => $request->input('reason', 'Eintrag gelöscht'),
        ]);

        $entry->delete();

        return response()->json([
            'message' => 'Eintrag erfolgreich gelöscht',
        ]);
    }

    public function getAuditLog($id)
    {
        $entry = TimeEntry::findOrFail($id);
        $audits = $entry->audits()->with('user')->orderBy('created_at', 'desc')->get();

        return response()->json($audits);
    }
}