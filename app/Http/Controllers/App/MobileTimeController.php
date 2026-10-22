<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\Request;

class MobileTimeController extends Controller
{
    public function status(Request $request)
    {
        $employee = $this->employee($request);
        $entry = TimeEntry::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        return response()->json([
            'clocked_in' => (bool) $entry,
            'entry' => $entry,
        ]);
    }

    public function clockIn(Request $request)
    {
        $employee = $this->employee($request);
        $open = TimeEntry::where('employee_id', $employee->id)->whereNull('clock_out')->exists();
        abort_if($open, 409, 'Du bist bereits eingestempelt.');

        $shift = $employee->shifts()
            ->whereDate('shift_date', today())
            ->orderBy('start_time')
            ->first();

        $entry = TimeEntry::create([
            'employee_id' => $employee->id,
            'shift_id' => $shift?->id,
            'clock_in' => now(),
            'is_manual' => false,
        ]);

        return response()->json(['entry' => $entry], 201);
    }

    public function clockOut(Request $request)
    {
        $employee = $this->employee($request);
        $entry = TimeEntry::clockOut($employee);
        abort_unless($entry, 409, 'Du bist aktuell nicht eingestempelt.');

        return response()->json(['entry' => $entry->fresh()]);
    }

    public function history(Request $request)
    {
        $employee = $this->employee($request);
        $entries = TimeEntry::with('shift:id,shift_date,start_time,end_time')
            ->where('employee_id', $employee->id)
            ->latest('clock_in')
            ->limit(60)
            ->get();

        return response()->json(['entries' => $entries]);
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $employee->active, 403, 'Kein aktiver Mitarbeiter hinterlegt.');

        return $employee;
    }
}
