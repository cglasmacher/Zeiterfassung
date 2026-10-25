<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftApplication;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MobileShiftApplicationController extends Controller
{
    public function index(Request $request)
    {
        $employee = $this->employee($request);
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = isset($validated['from']) ? Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay() : today();
        $to = isset($validated['to']) ? Carbon::createFromFormat('Y-m-d', $validated['to'])->startOfDay() : $from->copy()->addDays(6);

        abort_if($to->lt($from), 422, 'Das Enddatum darf nicht vor dem Startdatum liegen.');
        abort_if($from->diffInDays($to) > 92, 422, 'Der abgefragte Zeitraum darf maximal 93 Tage umfassen.');

        $shifts = Shift::query()
            ->with(['department:id,name', 'shiftType:id,name'])
            ->with(['applications' => function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->select(['id', 'shift_id', 'employee_id', 'status', 'created_at', 'decided_at']);
            }])
            ->whereNull('employee_id')
            ->where('is_open_for_applications', true)
            ->whereBetween('shift_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Shift $shift) => $this->serializeShift($shift));

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'shifts' => $shifts,
        ]);
    }

    public function apply(Request $request, Shift $shift)
    {
        $employee = $this->employee($request);

        abort_unless($shift->is_open_for_applications && $shift->employee_id === null, 409, 'Diese Schicht ist nicht mehr verfügbar.');
        abort_if(Carbon::parse($shift->shift_date)->lt(today()), 422, 'Für vergangene Schichten ist keine Bewerbung möglich.');

        $application = ShiftApplication::firstOrCreate(
            ['shift_id' => $shift->id, 'employee_id' => $employee->id],
            ['status' => ShiftApplication::STATUS_PENDING]
        );

        if (!$application->wasRecentlyCreated && $application->status === ShiftApplication::STATUS_REJECTED) {
            $application->update([
                'status' => ShiftApplication::STATUS_PENDING,
                'decided_at' => null,
            ]);
        }

        return response()->json([
            'message' => 'Bewerbung wurde übermittelt.',
            'application' => [
                'id' => $application->id,
                'shift_id' => $application->shift_id,
                'status' => $application->status,
                'created_at' => $application->created_at,
                'decided_at' => $application->decided_at,
            ],
        ], $application->wasRecentlyCreated ? 201 : 200);
    }

    public function withdraw(Request $request, Shift $shift)
    {
        $employee = $this->employee($request);

        $application = ShiftApplication::query()
            ->where('shift_id', $shift->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        abort_if($application->status === ShiftApplication::STATUS_APPROVED, 409, 'Eine bereits bestätigte Schicht kann nicht über die Bewerbung zurückgezogen werden.');

        $application->delete();

        return response()->json(['message' => 'Bewerbung wurde zurückgezogen.']);
    }

    private function serializeShift(Shift $shift): array
    {
        $application = $shift->applications->first();

        return [
            'id' => $shift->id,
            'shift_date' => $shift->shift_date,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'planned_hours' => $shift->planned_hours,
            'department' => $shift->department ? [
                'id' => $shift->department->id,
                'name' => $shift->department->name,
            ] : null,
            'shift_type' => $shift->shiftType ? [
                'id' => $shift->shiftType->id,
                'name' => $shift->shiftType->name,
            ] : null,
            'application' => $application ? [
                'id' => $application->id,
                'status' => $application->status,
                'created_at' => $application->created_at,
                'decided_at' => $application->decided_at,
            ] : null,
        ];
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $employee->active, 403, 'Kein aktiver Mitarbeiter hinterlegt.');

        return $employee;
    }
}
