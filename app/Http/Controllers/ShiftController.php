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

        $shifts = Shift::with(['employee', 'shiftType'])
            ->whereBetween('shift_date', [$start, $end])
            ->get();

        $employees = Employee::where('active', true)->get();
        $types = ShiftType::where('active', true)->get();

        return response()->json([
            'shifts' => $shifts,
            'employees' => $employees,
            'shift_types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_type_id' => 'required|exists:shift_types,id',
            'shift_date' => 'required|date',
        ]);

        $type = ShiftType::find($data['shift_type_id']);
        $shift = Shift::create([
            ...$data,
            'start_time' => $type->default_start,
            'end_time' => $type->default_end,
            'planned_hours' => $type->default_hours,
        ]);

        return response()->json(['shift' => $shift->load('employee', 'shiftType')]);
    }

    public function update(Request $request, $id)
    {
        $shift = Shift::findOrFail($id);
        $shift->update($request->only(['employee_id', 'shift_type_id', 'shift_date', 'start_time', 'end_time']));
        return response()->json(['shift' => $shift->load('employee', 'shiftType')]);
    }

    public function destroy($id)
    {
        Shift::findOrFail($id)->delete();
        return response()->json(['status' => 'deleted']);
    }
}
