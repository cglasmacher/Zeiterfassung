<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Employee, Shift, TimeEntry};
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        // Aktive Mitarbeiter
        $activeEmployees = Employee::where('active', true)->count();

        // Heutige offene Einträge (eingestempelt)
        $presentToday = TimeEntry::whereDate('clock_in', $today)
            ->whereNull('clock_out')
            ->distinct('employee_id')
            ->count('employee_id');

        // Gesamte Arbeitsstunden heute
        $totalHoursToday = TimeEntry::whereDate('clock_in', $today)
            ->whereNotNull('clock_out')
            ->sum('total_hours');

        // Noch offene Stunden (aktuell eingestempelt)
        $openEntries = TimeEntry::whereDate('clock_in', $today)
            ->whereNull('clock_out')
            ->get();
        
        foreach ($openEntries as $entry) {
            $hoursSinceIn = Carbon::parse($entry->clock_in)->diffInMinutes(now()) / 60;
            $totalHoursToday += $hoursSinceIn;
        }

        // Geplante Schichten diese Woche
        $scheduledShifts = Shift::whereBetween('shift_date', [$weekStart, $weekEnd])->count();

        // Überstunden diese Woche (Durchschnitt pro MA)
        $weeklyEntries = TimeEntry::whereBetween('clock_in', [$weekStart, $weekEnd])
            ->whereNotNull('clock_out')
            ->selectRaw('employee_id, SUM(total_hours) as total')
            ->groupBy('employee_id')
            ->get();
        
        $overtimeTotal = 0;
        $employeesWithOvertime = 0;
        foreach ($weeklyEntries as $entry) {
            $overtime = max(0, $entry->total - 40); // Über 40h = Überstunden
            if ($overtime > 0) {
                $overtimeTotal += $overtime;
                $employeesWithOvertime++;
            }
        }
        $weeklyOvertime = $employeesWithOvertime > 0 ? $overtimeTotal / $employeesWithOvertime : 0;

        // Abwesenheiten heute (geplante Schichten ohne Zeiteinträge)
        $scheduledToday = Shift::whereDate('shift_date', $today)
            ->whereNotNull('employee_id')
            ->pluck('employee_id')
            ->unique();
        
        $presentEmployeeIds = TimeEntry::whereDate('clock_in', $today)
            ->pluck('employee_id')
            ->unique();
        
        $absentToday = $scheduledToday->diff($presentEmployeeIds)->count();

        return response()->json([
            'activeEmployees' => $activeEmployees,
            'totalHoursToday' => round($totalHoursToday, 1),
            'scheduledShifts' => $scheduledShifts,
            'weeklyOvertime' => round($weeklyOvertime, 1),
            'presentToday' => $presentToday,
            'absentToday' => $absentToday,
        ]);
    }

    public function absences()
    {
        $today = Carbon::today();
        
        // Mitarbeiter mit geplanten Schichten heute, aber ohne Zeiteinträge
        $scheduledEmployees = Shift::whereDate('shift_date', $today)
            ->whereNotNull('employee_id')
            ->with('employee')
            ->get()
            ->pluck('employee')
            ->unique('id');
        
        $presentEmployeeIds = TimeEntry::whereDate('clock_in', $today)
            ->pluck('employee_id')
            ->unique();
        
        $absences = $scheduledEmployees->filter(function($employee) use ($presentEmployeeIds) {
            return !$presentEmployeeIds->contains($employee->id);
        })->map(function($employee) {
            return [
                'name' => $employee->first_name . ' ' . $employee->last_name,
                'reason' => 'Nicht erschienen', // Könnte erweitert werden mit Urlaubsverwaltung
                'color' => 'error'
            ];
        })->values();

        return response()->json($absences);
    }

    public function alerts()
    {
        $tomorrow = Carbon::tomorrow();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $alerts = [];

        // Offene Schichten für morgen
        $openShiftsTomorrow = Shift::whereDate('shift_date', $tomorrow)
            ->whereNull('employee_id')
            ->count();
        
        if ($openShiftsTomorrow > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Offene Schichten',
                'message' => "$openShiftsTomorrow Schichten für morgen noch nicht besetzt"
            ];
        }

        // Mitarbeiter über Überstunden-Limit
        $weeklyEntries = TimeEntry::whereBetween('clock_in', [$weekStart, $weekEnd])
            ->whereNotNull('clock_out')
            ->selectRaw('employee_id, SUM(total_hours) as total')
            ->groupBy('employee_id')
            ->having('total', '>', 40)
            ->count();
        
        if ($weeklyEntries > 0) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Überstunden-Limit',
                'message' => "$weeklyEntries Mitarbeiter über 40h/Woche"
            ];
        }

        // Mitarbeiter über monatlichem Stundenkontingent
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        
        $monthlyHours = Shift::whereBetween('shift_date', [$monthStart, $monthEnd])
            ->selectRaw('employee_id, SUM(planned_hours) as total_hours')
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');
        
        $overloadedCount = Employee::where('active', true)
            ->whereNotNull('max_monthly_hours')
            ->get()
            ->filter(function($emp) use ($monthlyHours) {
                $planned = $monthlyHours[$emp->id]->total_hours ?? 0;
                return $planned > $emp->max_monthly_hours;
            })
            ->count();
        
        if ($overloadedCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Monatliches Stundenlimit',
                'message' => "$overloadedCount Mitarbeiter über ihrem monatlichen Limit"
            ];
        }

        return response()->json($alerts);
    }

    public function recentActivity()
    {
        $activities = [];

        // Letzte Zeiteinträge
        $recentEntries = TimeEntry::with('employee')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        foreach ($recentEntries as $entry) {
            $activities[] = [
                'action' => $entry->clock_out ? 'MA ausgestempelt' : 'MA eingestempelt',
                'time' => $entry->created_at->diffForHumans(),
                'user' => $entry->employee->first_name . ' ' . $entry->employee->last_name
            ];
        }

        // Letzte Schichten
        $recentShifts = Shift::with('employee')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
        
        foreach ($recentShifts as $shift) {
            $activities[] = [
                'action' => 'Schicht erstellt',
                'time' => $shift->created_at->diffForHumans(),
                'user' => $shift->employee ? $shift->employee->first_name . ' ' . $shift->employee->last_name : 'Offen'
            ];
        }

        // Sortiere nach Zeit
        usort($activities, function($a, $b) {
            return strtotime($a['time']) - strtotime($b['time']);
        });

        return response()->json(array_slice($activities, 0, 5));
    }
}