<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShiftType;

class ShiftTypeController extends Controller
{
    public function index()
    {
        $shiftTypes = ShiftType::with('departments')->orderBy('name')->get();
        return response()->json($shiftTypes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'default_start' => 'required|date_format:H:i',
            'default_end' => 'required|date_format:H:i',
            'default_break_minutes' => 'required|numeric|min:0',
            'active' => 'boolean',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
        ]);

        $departmentIds = $data['department_ids'] ?? [];
        unset($data['department_ids']);

        $shiftType = ShiftType::create($data);
        
        if (!empty($departmentIds)) {
            $shiftType->departments()->sync($departmentIds);
        }

        return response()->json($shiftType->load('departments'));
    }

    public function update(Request $request, $id)
    {
        $shiftType = ShiftType::findOrFail($id);
        
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'default_start' => 'required|date_format:H:i',
            'default_end' => 'required|date_format:H:i',
            'default_break_minutes' => 'required|numeric|min:0',
            'active' => 'boolean',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
        ]);

        $departmentIds = $data['department_ids'] ?? [];
        unset($data['department_ids']);

        $shiftType->update($data);
        
        if (isset($departmentIds)) {
            $shiftType->departments()->sync($departmentIds);
        }

        return response()->json($shiftType->load('departments'));
    }

    public function destroy($id)
    {
        $shiftType = ShiftType::findOrFail($id);
        $shiftType->delete();
        return response()->json(['status' => 'deleted']);
    }
}