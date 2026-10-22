<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\InfoItem;
use App\Models\Shift;
use App\Models\TimeEntry;
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

        $shifts = Shift::with(['department', 'shiftType'])
            ->where('employee_id', $employee->id)
            ->whereDate('shift_date', '>=', today())
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->limit(60)
            ->get();

        return response()->json(['shifts' => $shifts]);
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $employee->active, 403, 'Kein aktiver Mitarbeiter hinterlegt.');

        return $employee;
    }
}
