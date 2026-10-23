<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ShiftApplication;
use App\Models\ShiftType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlannerShiftApplicationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $shifts = Shift::query()
            ->with([
                'shiftType:id,name',
                'department:id,name',
                'employee:id,first_name,last_name',
                'applications.employee:id,first_name,last_name',
            ])
            ->whereBetween('shift_date', [$data['start'], $data['end']])
            ->where(function ($query) {
                $query->where('is_open_for_applications', true)
                    ->orWhereHas('applications');
            })
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'shifts' => $shifts->map(fn (Shift $shift) => [
                'id' => $shift->id,
                'shift_date' => $shift->shift_date,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'is_open_for_applications' => (bool) $shift->is_open_for_applications,
                'shift_type' => $shift->shiftType ? [
                    'id' => $shift->shiftType->id,
                    'name' => $shift->shiftType->name,
                ] : null,
                'department' => $shift->department ? [
                    'id' => $shift->department->id,
                    'name' => $shift->department->name,
                ] : null,
                'employee' => $shift->employee ? [
                    'id' => $shift->employee->id,
                    'first_name' => $shift->employee->first_name,
                    'last_name' => $shift->employee->last_name,
                ] : null,
                'applications' => $shift->applications->map(fn (ShiftApplication $application) => [
                    'id' => $application->id,
                    'status' => $application->status,
                    'decided_at' => $application->decided_at,
                    'employee' => [
                        'id' => $application->employee->id,
                        'first_name' => $application->employee->first_name,
                        'last_name' => $application->employee->last_name,
                    ],
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateShift($request);
        $type = ShiftType::findOrFail($data['shift_type_id']);

        $shift = Shift::create([
            ...$data,
            'employee_id' => null,
            'planned_hours' => $this->plannedHours($data['start_time'], $data['end_time'], $type->default_break_minutes ?? 0),
            'status' => 'planned',
            'is_open_for_applications' => true,
        ]);

        return response()->json(['shift' => $shift], 201);
    }

    public function update(Request $request, Shift $shift)
    {
        abort_if($shift->employee_id !== null, 409, 'Eine bereits vergebene Schicht kann hier nicht als freie Schicht bearbeitet werden.');

        $data = $this->validateShift($request);
        $type = ShiftType::findOrFail($data['shift_type_id']);

        $shift->update([
            ...$data,
            'employee_id' => null,
            'planned_hours' => $this->plannedHours($data['start_time'], $data['end_time'], $type->default_break_minutes ?? 0),
            'is_open_for_applications' => $request->boolean('is_open_for_applications', true),
        ]);

        return response()->json([
            'shift' => $shift->fresh()->load(['shiftType', 'department', 'applications.employee']),
        ]);
    }

    public function approve(Shift $shift, ShiftApplication $application)
    {
        abort_unless($application->shift_id === $shift->id, 404);

        DB::transaction(function () use ($shift, $application) {
            $lockedShift = Shift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
            $lockedApplication = ShiftApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

            abort_if($lockedShift->employee_id !== null, 409, 'Diese Schicht wurde bereits vergeben.');
            abort_unless($lockedShift->is_open_for_applications, 409, 'Diese Schicht ist nicht mehr freigegeben.');
            abort_unless($lockedApplication->shift_id === $lockedShift->id, 404);
            abort_unless($lockedApplication->status === ShiftApplication::STATUS_PENDING, 409, 'Diese Bewerbung ist nicht mehr offen.');

            $lockedShift->update([
                'employee_id' => $lockedApplication->employee_id,
                'is_open_for_applications' => false,
            ]);

            $lockedApplication->update([
                'status' => ShiftApplication::STATUS_APPROVED,
                'decided_at' => now(),
            ]);

            ShiftApplication::query()
                ->where('shift_id', $lockedShift->id)
                ->whereKeyNot($lockedApplication->id)
                ->where('status', ShiftApplication::STATUS_PENDING)
                ->update([
                    'status' => ShiftApplication::STATUS_REJECTED,
                    'decided_at' => now(),
                ]);
        });

        return response()->json([
            'message' => 'Schicht wurde verbindlich zugewiesen.',
            'shift' => $shift->fresh()->load('employee'),
        ]);
    }

    private function validateShift(Request $request): array
    {
        return $request->validate([
            'shift_type_id' => ['required', 'exists:shift_types,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'shift_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_open_for_applications' => ['sometimes', 'boolean'],
        ]);
    }

    private function plannedHours(string $startTime, string $endTime, int $breakMinutes): float
    {
        $start = strtotime($startTime);
        $end = strtotime($endTime);
        if ($end < $start) {
            $end += 86400;
        }

        return max((($end - $start) / 3600) - ($breakMinutes / 60), 0);
    }
}
