<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\InfoItem;
use App\Models\Shift;
use App\Models\ShiftApplication;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $employee = $this->employee($request);

        $todayShift = Shift::with(['department', 'shiftType'])
            ->where('employee_id', $employee->id)
            ->whereDate('shift_date', today())
            ->orderBy('start_time')
            ->first();

        $nextShift = Shift::with(['department', 'shiftType'])
            ->where('employee_id', $employee->id)
            ->whereDate('shift_date', '>', today())
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->first();

        $openEntry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        $openTasks = InfoItem::query()
            ->where('active', true)
            ->whereNull('completed_at')
            ->where(function ($query) use ($employee) {
                $query->whereNull('employee_id')->orWhere('employee_id', $employee->id);
            })
            ->where('kind', 'task')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->count();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'position' => $employee->position,
            ],
            'today_shift' => $todayShift,
            'next_shift' => $nextShift,
            'time' => [
                'clocked_in' => (bool) $openEntry,
                'entry' => $openEntry,
            ],
            'open_tasks' => $openTasks,
        ]);
    }

    public function shifts(Request $request)
    {
        $employee = $this->employee($request);
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = isset($validated['from']) ? Carbon::createFromFormat('Y-m-d', $validated['from'])->startOfDay() : today();
        $to = isset($validated['to']) ? Carbon::createFromFormat('Y-m-d', $validated['to'])->startOfDay() : $from->copy()->addDays(59);

        abort_if($to->lt($from), 422, 'Das Enddatum darf nicht vor dem Startdatum liegen.');
        abort_if($from->diffInDays($to) > 92, 422, 'Der abgefragte Zeitraum darf maximal 93 Tage umfassen.');

        $shifts = Shift::with([
                'department',
                'shiftType',
                'applications' => fn ($query) => $query
                    ->where('employee_id', $employee->id)
                    ->where('status', ShiftApplication::STATUS_APPROVED),
            ])
            ->where('employee_id', $employee->id)
            ->whereBetween('shift_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Shift $shift) {
                $shift->setAttribute('from_application', $shift->applications->isNotEmpty());
                $shift->unsetRelation('applications');

                return $shift;
            });

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'shifts' => $shifts,
        ]);
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $employee->active, 403, 'Kein aktiver Mitarbeiter hinterlegt.');

        return $employee;
    }
}
