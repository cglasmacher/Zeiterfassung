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

        return response()->json([
            'shifts' => $shifts,
            'employees' => $employees,
            'shift_types' => $types,
            'departments' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'department_id' => 'nullable|exists:departments,id',
            'shift_type_id' => 'required|exists:shift_types,id',
            'shift_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ]);

        $type = ShiftType::find($data['shift_type_id']);
        
        $shift = Shift::create([
            'employee_id' => $data['employee_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'shift_type_id' => $data['shift_type_id'],
            'shift_date' => $data['shift_date'],
            'start_time' => $data['start_time'] ?? $type->default_start,
            'end_time' => $data['end_time'] ?? $type->default_end,
            'planned_hours' => $this->calculatePlannedHours(
                $data['start_time'] ?? $type->default_start,
                $data['end_time'] ?? $type->default_end,
                $type->default_break_minutes
            ),
            'status' => 'planned',
        ]);

        return response()->json(['shift' => $shift->load('employee', 'shiftType', 'department')]);
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
}
