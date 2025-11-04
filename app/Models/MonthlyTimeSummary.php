<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MonthlyTimeSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'total_hours',
        'total_break_minutes',
        'overtime_hours',
        'working_days',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Berechnet oder aktualisiert die Monatswerte eines Mitarbeiters
     */
    public static function updateForMonth(int $employeeId, Carbon $date): self
    {
        $year = $date->year;
        $month = $date->month;

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $daily = \App\Models\DailyTimeSummary::where('employee_id', $employeeId)
            ->whereBetween('work_date', [$start, $end])
            ->get();

        $totalHours = $daily->sum('total_hours');
        $totalBreaks = $daily->sum('total_break_minutes');
        $workingDays = $daily->count();

        return self::updateOrCreate(
            ['employee_id' => $employeeId, 'year' => $year, 'month' => $month],
            [
                'total_hours' => round($totalHours, 2),
                'total_break_minutes' => round($totalBreaks, 2),
                'working_days' => $workingDays,
            ]
        );
    }

    /**
     * Optional: Liefert eine schön formatierte Anzeige
     */
    public function getLabelAttribute(): string
    {
        return sprintf('%02d/%d', $this->month, $this->year);
    }
}
