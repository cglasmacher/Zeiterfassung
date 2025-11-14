<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Department;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::with('departments')->orderBy('last_name')->get();
        return response()->json($employees);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:employees,email',
            'phone' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'employment_type' => 'required|in:permanent,temporary',
            'hourly_rate' => 'nullable|numeric|min:0',
            'rfid_tag' => 'nullable|string|unique:employees,rfid_tag',
            'active' => 'boolean',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
        ]);

        $departmentIds = $data['department_ids'] ?? [];
        unset($data['department_ids']);

        $employee = Employee::create($data);
        
        if (!empty($departmentIds)) {
            $employee->departments()->sync($departmentIds);
        }

        return response()->json($employee->load('departments'));
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:employees,email,' . $id,
            'phone' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'employment_type' => 'required|in:permanent,temporary',
            'hourly_rate' => 'nullable|numeric|min:0',
            'rfid_tag' => 'nullable|string|unique:employees,rfid_tag,' . $id,
            'active' => 'boolean',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'exists:departments,id',
        ]);

        $departmentIds = $data['department_ids'] ?? [];
        unset($data['department_ids']);

        $employee->update($data);
        
        if (isset($departmentIds)) {
            $employee->departments()->sync($departmentIds);
        }

        return response()->json($employee->load('departments'));
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();
        return response()->json(['status' => 'deleted']);
    }
}