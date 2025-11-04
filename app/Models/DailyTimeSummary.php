<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DailyTimeSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_date',
        'total_hours',
        'total_break_minutes',
        'segments',
        'overtime_hours',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Aktualisiert oder erstellt automatisch den Tages-Datensatz
     */
    public static function updateForDay(int $employeeId, Carbon $date): self
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay   = $date->copy()->endOfDay();

        $entries = \App\Models\TimeEntry::where('employee_id', $employeeId)
            ->whereBetween('clock_in', [$startOfDay, $endOfDay])
            ->whereNotNull('clock_out')
            ->get();

        $segments = $entries->count();
        $totalHours = $entries->sum(fn($e) => $e->hours_worked ?? 0);
        $totalBreaks = \App\Models\BreakRule::calculateBreakForHours($totalHours);

        return self::updateOrCreate(
            ['employee_id' => $employeeId, 'work_date' => $date->toDateString()],
            [
                'total_hours' => round($totalHours - ($totalBreaks / 60), 2),
                'total_break_minutes' => $totalBreaks,
                'segments' => $segments,
            ]
        );
    }
}
