<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Shift, ShiftType, Employee};
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

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

        // Lade Einstellungen für geschlossene Tage
        $closedDays = \App\Models\Setting::get('closed_days', []);

        return response()->json([
            'shifts' => $shifts,
            'employees' => $employees,
            'shift_types' => $types,
            'departments' => $departments,
            'closed_days' => $closedDays,
        ]);
    }

    public function store(Request $request)
    {
        try {
            \Log::info('Shift store request:', $request->all());
            
            $data = $request->validate([
                'employee_id' => 'nullable|exists:employees,id',
                'department_id' => 'nullable|exists:departments,id',
                'shift_type_id' => 'required|exists:shift_types,id',
                'shift_date' => 'required|date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
            ]);

            \Log::info('Validated data:', $data);

            $type = ShiftType::with('departments')->find($data['shift_type_id']);
            
            if (!$type) {
                \Log::error('ShiftType not found: ' . $data['shift_type_id']);
                return response()->json(['error' => 'Schichttyp nicht gefunden'], 404);
            }
            
            \Log::info('ShiftType found:', $type->toArray());
            
            // Wenn keine department_id angegeben wurde, nimm das erste Department vom ShiftType
            if (empty($data['department_id']) && $type->departments && $type->departments->isNotEmpty()) {
                $data['department_id'] = $type->departments->first()->id;
                \Log::info('Auto-assigned department_id from ShiftType: ' . $data['department_id']);
            }
            
            $startTime = $data['start_time'] ?? $type->default_start;
            $endTime = $data['end_time'] ?? $type->default_end;
            $breakMinutes = $type->default_break_minutes ?? 0;
            
            \Log::info('Calculated times:', [
                'start' => $startTime,
                'end' => $endTime,
                'break' => $breakMinutes
            ]);
            
            $plannedHours = $this->calculatePlannedHours($startTime, $endTime, $breakMinutes);
            
            \Log::info('Planned hours: ' . $plannedHours);
            
            $shiftData = [
                'employee_id' => $data['employee_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'shift_type_id' => $data['shift_type_id'],
                'shift_date' => $data['shift_date'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'planned_hours' => $plannedHours,
                'status' => 'planned',
            ];
            
            \Log::info('Creating shift with data:', $shiftData);
            
            $shift = Shift::create($shiftData);

            \Log::info('Shift created successfully:', $shift->toArray());

            return response()->json(['shift' => $shift->load('employee', 'shiftType', 'department')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error:', $e->errors());
            return response()->json(['error' => 'Validierungsfehler', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Log::error('Error creating shift: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
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
        
        // Wenn shift_type_id geändert wird und keine department_id angegeben wurde
        if (isset($data['shift_type_id']) && empty($data['department_id'])) {
            $type = ShiftType::with('departments')->find($data['shift_type_id']);
            if ($type && $type->departments && $type->departments->isNotEmpty()) {
                $data['department_id'] = $type->departments->first()->id;
            }
        }
        
        // Wenn Zeiten geändert werden, berechne planned_hours neu
        if (isset($data['start_time']) || isset($data['end_time'])) {
            $shiftType = isset($data['shift_type_id']) ? ShiftType::find($data['shift_type_id']) : $shift->shiftType;
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

    public function copyWeek(Request $request)
    {
        $data = $request->validate([
            'source_start' => 'required|date',
            'target_start' => 'required|date',
        ]);

        $sourceStart = Carbon::parse($data['source_start']);
        $sourceEnd = $sourceStart->copy()->addDays(6);
        $targetStart = Carbon::parse($data['target_start']);

        // Hole alle Schichten der Quellwoche
        $sourceShifts = Shift::whereBetween('shift_date', [$sourceStart, $sourceEnd])->get();

        $copiedCount = 0;
        foreach ($sourceShifts as $shift) {
            $dayOffset = Carbon::parse($shift->shift_date)->diffInDays($sourceStart);
            $newDate = $targetStart->copy()->addDays($dayOffset);

            Shift::create([
                'employee_id' => $shift->employee_id,
                'department_id' => $shift->department_id,
                'shift_type_id' => $shift->shift_type_id,
                'shift_date' => $newDate,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'planned_hours' => $shift->planned_hours,
                'status' => 'planned',
            ]);
            $copiedCount++;
        }

        return response()->json([
            'message' => "$copiedCount Schichten kopiert",
            'copied_count' => $copiedCount,
        ]);
    }

    public function saveTemplate(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'week_start' => 'required|date',
        ]);

        $weekStart = Carbon::parse($data['week_start']);
        $weekEnd = $weekStart->copy()->addDays(6);

        \Log::info('Save Template - Week Start: ' . $weekStart->format('Y-m-d') . ', Week End: ' . $weekEnd->format('Y-m-d'));

        $shifts = Shift::with(['employee', 'shiftType', 'department'])
            ->whereBetween('shift_date', [$weekStart, $weekEnd])
            ->get();

        \Log::info('Save Template - Found ' . $shifts->count() . ' shifts');

        $templateData = $shifts->map(function($shift) use ($weekStart) {
            $dayOffset = Carbon::parse($shift->shift_date)->diffInDays($weekStart);
            $data = [
                'day_offset' => $dayOffset,
                'employee_id' => $shift->employee_id,
                'department_id' => $shift->department_id,
                'shift_type_id' => $shift->shift_type_id,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'planned_hours' => $shift->planned_hours,
            ];
            \Log::info('Save Template - Shift: ' . $shift->id . ', Date: ' . $shift->shift_date . ', Day Offset: ' . $dayOffset, $data);
            return $data;
        })->toArray();

        $template = \App\Models\ShiftTemplate::create([
            'name' => $data['name'],
            'template_data' => $templateData,
        ]);

        return response()->json([
            'message' => 'Vorlage gespeichert',
            'template' => $template,
        ]);
    }

    public function getTemplates()
    {
        $templates = \App\Models\ShiftTemplate::orderBy('created_at', 'desc')->get();
        return response()->json($templates);
    }

    public function loadTemplate(Request $request, $id)
    {
        $data = $request->validate([
            'week_start' => 'required|date',
        ]);

        $template = \App\Models\ShiftTemplate::findOrFail($id);
        $weekStart = Carbon::parse($data['week_start']);

        \Log::info('Load Template - Template: ' . $template->name . ', Target Week Start: ' . $weekStart->format('Y-m-d'));
        \Log::info('Load Template - Template Data Count: ' . count($template->template_data));

        $createdCount = 0;
        foreach ($template->template_data as $shiftData) {
            $shiftDate = $weekStart->copy()->addDays($shiftData['day_offset']);

            \Log::info('Load Template - Creating shift with day_offset: ' . $shiftData['day_offset'] . ', Target Date: ' . $shiftDate->format('Y-m-d'));

            Shift::create([
                'employee_id' => $shiftData['employee_id'],
                'department_id' => $shiftData['department_id'] ?? null,
                'shift_type_id' => $shiftData['shift_type_id'],
                'shift_date' => $shiftDate,
                'start_time' => $shiftData['start_time'],
                'end_time' => $shiftData['end_time'],
                'planned_hours' => $shiftData['planned_hours'],
                'status' => 'planned',
            ]);
            $createdCount++;
        }

        \Log::info('Load Template - Created ' . $createdCount . ' shifts');

        return response()->json([
            'message' => "$createdCount Schichten aus Vorlage geladen",
            'created_count' => $createdCount,
        ]);
    }

    public function deleteTemplate($id)
    {
        $template = \App\Models\ShiftTemplate::findOrFail($id);
        $template->delete();
        return response()->json(['message' => 'Vorlage gelöscht']);
    }

    public function exportPDF(Request $request)
    {
        $data = $request->validate([
            'week_start' => 'required|date',
        ]);

        $weekStart = Carbon::parse($data['week_start']);
        $weekEnd = $weekStart->copy()->addDays(6);

        $shifts = Shift::with(['employee', 'shiftType', 'department'])
            ->whereBetween('shift_date', [$weekStart, $weekEnd])
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->get();

        $departments = \App\Models\Department::all();
        $shiftTypes = ShiftType::where('active', true)
            ->orderByRaw('print_num IS NULL, print_num ASC, id ASC')
            ->get();

        // Lade Einstellungen für geschlossene Tage
        $closedDays = \App\Models\Setting::get('closed_days', []);
        
        // Filtere Tage basierend auf geschlossenen Tagen
        $days = array_filter(
            array_map(fn($i) => $weekStart->copy()->addDays($i), range(0, 6)),
            fn($day) => !in_array($day->dayOfWeek, $closedDays)
        );
        $days = array_values($days); // Re-index

        // Gruppiere Schichten nach Bereich -> Tag -> Schichttyp
        $shiftsByDepartment = [];
        
        // Füge eine "Ohne Bereich" Kategorie hinzu für Schichten ohne department_id
        $allDepartments = $departments->toArray();
        array_unshift($allDepartments, (object)['id' => null, 'name' => 'Ohne Bereich']);
        
        foreach ($allDepartments as $department) {
            $deptId = is_object($department) ? $department->id : $department['id'];
            $deptName = is_object($department) ? $department->name : $department['name'];
            
            $shiftsByDepartment[$deptId ?? 'null'] = [
                'department' => (object)['id' => $deptId, 'name' => $deptName],
                'grid' => [] // [date][shift_type_id] = [employees]
            ];
            
            foreach ($days as $day) {
                $dateStr = $day->format('Y-m-d');
                $shiftsByDepartment[$deptId ?? 'null']['grid'][$dateStr] = [];
                
                foreach ($shiftTypes as $shiftType) {
                    // Finde alle Mitarbeiter für diesen Tag, Bereich und Schichttyp
                    $dayShifts = $shifts->filter(function($shift) use ($deptId, $day, $shiftType) {
                        return $shift->department_id === $deptId &&
                               Carbon::parse($shift->shift_date)->isSameDay($day) &&
                               $shift->shift_type_id === $shiftType->id;
                    });
                    
                    $employees = $dayShifts->map(function($shift) {
                        return $shift->employee ? $shift->employee->first_name . ' ' . substr($shift->employee->last_name, 0, 1) . '.' : 'Offen';
                    })->toArray();
                    
                    $shiftsByDepartment[$deptId ?? 'null']['grid'][$dateStr][$shiftType->id] = $employees;
                }
            }
        }

        try {
            $pdf = Pdf::loadView('exports.shift-plan', [
                'weekStart' => $weekStart,
                'weekEnd' => $weekEnd,
                'shiftsByDepartment' => $shiftsByDepartment,
                'shiftTypes' => $shiftTypes,
                'days' => $days,
            ]);
            
            $pdf->setPaper('a4', 'landscape');

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Dienstplan_' . $weekStart->format('Y-m-d') . '.pdf"',
            ]);
        } catch (\Exception $e) {
            \Log::error('PDF generation error: ' . $e->getMessage());
            
            // Fallback: Return HTML if PDF fails
            $html = view('exports.shift-plan', [
                'weekStart' => $weekStart,
                'weekEnd' => $weekEnd,
                'shiftsByDepartment' => $shiftsByDepartment,
                'shiftTypes' => $shiftTypes,
                'days' => $days,
            ])->render();
            
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'inline');
        }
    }
}
