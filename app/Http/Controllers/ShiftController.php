<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Shift, ShiftType, Employee};
use Carbon\Carbon;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $start = Carbon::parse($request->query('start', now()->startOfWeek()));
        $end = Carbon::parse($request->query('end', now()->endOfWeek()));

        $shifts = Shift::with(['employee', 'shiftType', 'department'])
            ->whereBetween('shift_date', [$start, $end])
            ->get();

        $employees = Employee::where('active', true)
            ->with('departments')
            ->get();
            
        // Berechne geplante Stunden für jeden Mitarbeiter im aktuellen Monat
        $monthStart = Carbon::parse($start)->startOfMonth();
        $monthEnd = Carbon::parse($start)->endOfMonth();
        
        $monthlyHours = Shift::whereBetween('shift_date', [$monthStart, $monthEnd])
            ->selectRaw('employee_id, SUM(planned_hours) as total_hours')
            ->groupBy('employee_id')
            ->pluck('total_hours', 'employee_id');
        
        $employees = $employees->map(function($employee) use ($monthlyHours) {
            $employee->monthly_planned_hours = $monthlyHours[$employee->id] ?? 0;
            return $employee;
        });

        $types = ShiftType::where('active', true)->with('departments')->get();
        $departments = \App\Models\Department::all();

        // Lade Einstellungen für geschlossene Tage
        $closedDays = \App\Models\Setting::get('closed_days', []);

        return response()->json([
            'shifts' => $shifts,
            'employees' => $employees,
            'shift_types' => $types,
            'departments' => $departments,
            'closed_days' => $closedDays,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'employee_id' => 'nullable|exists:employees,id',
                'department_id' => 'nullable|exists:departments,id',
                'shift_type_id' => 'required|exists:shift_types,id',
                'shift_date' => 'required|date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
            ]);

            $type = ShiftType::find($data['shift_type_id']);
            
            if (!$type) {
                return response()->json(['error' => 'Schichttyp nicht gefunden'], 404);
            }
            
            $startTime = $data['start_time'] ?? $type->default_start;
            $endTime = $data['end_time'] ?? $type->default_end;
            $breakMinutes = $type->default_break_minutes ?? 0;
            
            $shift = Shift::create([
                'employee_id' => $data['employee_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'shift_type_id' => $data['shift_type_id'],
                'shift_date' => $data['shift_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'planned_hours' => $this->calculatePlannedHours($startTime, $endTime, $breakMinutes),
                'status' => 'planned',
            ]);

            return response()->json(['shift' => $shift->load('employee', 'shiftType', 'department')]);
        } catch (\Exception $e) {
            \Log::error('Error creating shift: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);
        
        $data = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'department_id' => 'nullable|exists:departments,id',
            'shift_type_id' => 'nullable|exists:shift_types,id',
            'shift_date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ]);
        
        // Wenn Zeiten geändert werden, berechne planned_hours neu
        if (isset($data['start_time']) || isset($data['end_time'])) {
            $shiftType = $shift->shiftType;
            $data['planned_hours'] = $this->calculatePlannedHours(
                $data['start_time'] ?? $shift->start_time,
                $data['end_time'] ?? $shift->end_time,
                $shiftType->default_break_minutes
            );
        }
        
        $shift->update($data);
        return response()->json(['shift' => $shift->load('employee', 'shiftType', 'department')]);
    }

    public function destroy($id)
    {
        Shift::findOrFail($id)->delete();
        return response()->json(['status' => 'deleted']);
    }
    
    private function calculatePlannedHours($startTime, $endTime, $breakMinutes)
    {
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        $diff = ($end - $start) / 3600 - ($breakMinutes / 60);
        return max($diff, 0);
    }

    public function copyWeek(Request $request)
    {
        $data = $request->validate([
            'source_start' => 'required|date',
            'target_start' => 'required|date',
        ]);

        $sourceStart = Carbon::parse($data['source_start']);
        $sourceEnd = $sourceStart->copy()->addDays(6);
        $targetStart = Carbon::parse($data['target_start']);

        // Hole alle Schichten der Quellwoche
        $sourceShifts = Shift::whereBetween('shift_date', [$sourceStart, $sourceEnd])->get();

        $copiedCount = 0;
        foreach ($sourceShifts as $shift) {
            $dayOffset = Carbon::parse($shift->shift_date)->diffInDays($sourceStart);
            $newDate = $targetStart->copy()->addDays($dayOffset);

            Shift::create([
                'employee_id' => $shift->employee_id,
                'department_id' => $shift->department_id,
                'shift_type_id' => $shift->shift_type_id,
                'shift_date' => $newDate,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'planned_hours' => $shift->planned_hours,
                'status' => 'planned',
            ]);
            $copiedCount++;
        }

        return response()->json([
            'message' => "$copiedCount Schichten kopiert",
            'copied_count' => $copiedCount,
        ]);
    }

    public function saveTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'week_start' => 'required|date',
        ]);

        $weekStart = Carbon::parse($data['week_start']);
        $weekEnd = $weekStart->copy()->addDays(6);

        $shifts = Shift::with(['employee', 'shiftType', 'department'])
            ->whereBetween('shift_date', [$weekStart, $weekEnd])
            ->get();

        $template = \App\Models\ShiftTemplate::create([
            'name' => $data['name'],
            'template_data' => $shifts->map(function($shift) use ($weekStart) {
                return [
                    'day_offset' => Carbon::parse($shift->shift_date)->diffInDays($weekStart),
                    'employee_id' => $shift->employee_id,
                    'department_id' => $shift->department_id,
                    'shift_type_id' => $shift->shift_type_id,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'planned_hours' => $shift->planned_hours,
                ];
            })->toArray(),
        ]);

        return response()->json([
            'message' => 'Vorlage gespeichert',
            'template' => $template,
        ]);
    }

    public function getTemplates()
    {
        $templates = \App\Models\ShiftTemplate::orderBy('created_at', 'desc')->get();
        return response()->json($templates);
    }

    public function loadTemplate(Request $request, $id)
    {
        $data = $request->validate([
            'week_start' => 'required|date',
        ]);

        $template = \App\Models\ShiftTemplate::findOrFail($id);
        $weekStart = Carbon::parse($data['week_start']);

        $createdCount = 0;
        foreach ($template->template_data as $shiftData) {
            $shiftDate = $weekStart->copy()->addDays($shiftData['day_offset']);

            Shift::create([
                'employee_id' => $shiftData['employee_id'],
                'department_id' => $shiftData['department_id'] ?? null,
                'shift_type_id' => $shiftData['shift_type_id'],
                'shift_date' => $shiftDate,
                'start_time' => $shiftData['start_time'],
                'end_time' => $shiftData['end_time'],
                'planned_hours' => $shiftData['planned_hours'],
                'status' => 'planned',
            ]);
            $createdCount++;
        }

        return response()->json([
            'message' => "$createdCount Schichten aus Vorlage geladen",
            'created_count' => $createdCount,
        ]);
    }

    public function deleteTemplate($id)
    {
        $template = \App\Models\ShiftTemplate::findOrFail($id);
        $template->delete();
        return response()->json(['message' => 'Vorlage gelöscht']);
    }

    public function exportPDF(Request $request)
    {
        $data = $request->validate([
            'week_start' => 'required|date',
        ]);

        $weekStart = Carbon::parse($data['week_start']);
        $weekEnd = $weekStart->copy()->addDays(6);

        $shifts = Shift::with(['employee', 'shiftType', 'department'])
            ->whereBetween('shift_date', [$weekStart, $weekEnd])
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get();

        $employees = Employee::where('active', true)->orderBy('last_name')->get();
        $shiftTypes = ShiftType::where('active', true)->orderBy('name')->get();

        // Gruppiere Schichten nach Mitarbeiter und Datum
        $shiftsByEmployee = [];
        foreach ($employees as $employee) {
            $shiftsByEmployee[$employee->id] = [
                'employee' => $employee,
                'shifts' => []
            ];
            for ($i = 0; $i < 7; $i++) {
                $date = $weekStart->copy()->addDays($i);
                $dayShifts = $shifts->filter(function($shift) use ($employee, $date) {
                    return $shift->employee_id === $employee->id && 
                           Carbon::parse($shift->shift_date)->isSameDay($date);
                });
                $shiftsByEmployee[$employee->id]['shifts'][$date->format('Y-m-d')] = $dayShifts;
            }
        }

        $html = view('exports.shift-plan', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'shiftsByEmployee' => $shiftsByEmployee,
            'days' => array_map(fn($i) => $weekStart->copy()->addDays($i), range(0, 6)),
        ])->render();

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="dienstplan_' . $weekStart->format('Y-m-d') . '.html"');
    }
}
