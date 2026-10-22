<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\InfoItem;
use Illuminate\Http\Request;

class MobileInfoController extends Controller
{
    public function tasks(Request $request)
    {
        $employee = $this->employee($request);

        return response()->json(['tasks' => $this->items($employee->id, 'task')
            ->orderByRaw('completed_at IS NOT NULL')
            ->orderByDesc('priority')
            ->orderBy('due_at')
            ->limit(100)
            ->get()]);
    }

    public function complete(Request $request, InfoItem $item)
    {
        $employee = $this->employee($request);
        abort_unless($item->kind === 'task', 422, 'Dieser Eintrag ist keine Aufgabe.');
        abort_unless($item->employee_id === null || (int) $item->employee_id === (int) $employee->id, 403);

        $data = $request->validate(['completed' => ['required', 'boolean']]);
        $item->update(['completed_at' => $data['completed'] ? now() : null]);

        return response()->json(['task' => $item->fresh()]);
    }

    public function announcements(Request $request)
    {
        $employee = $this->employee($request);

        return response()->json(['announcements' => $this->items($employee->id, 'notice')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit(100)
            ->get()]);
    }

    public function events(Request $request)
    {
        $employee = $this->employee($request);

        return response()->json(['events' => $this->items($employee->id, 'event')
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('starts_at')
            ->limit(100)
            ->get()]);
    }

    private function items(int $employeeId, string $kind)
    {
        return InfoItem::query()
            ->where('active', true)
            ->where('kind', $kind)
            ->where(function ($query) use ($employeeId) {
                $query->whereNull('employee_id')->orWhere('employee_id', $employeeId);
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            });
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee && $employee->active, 403, 'Kein aktiver Mitarbeiter hinterlegt.');

        return $employee;
    }
}
