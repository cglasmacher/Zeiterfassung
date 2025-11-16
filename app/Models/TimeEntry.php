<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'clock_in',
        'clock_out',
        'break_minutes',
        'hours_worked',
        'total_hours',
        'gross_wage',
        'admin_note',
        'override_hourly_rate',
        'is_manual',
        'paid_out_at',
    ];

    protected $casts = [
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'paid_out_at' => 'datetime',
    ];

    /** Beziehungen */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function audits()
    {
        return $this->hasMany(TimeEntryAudit::class);
    }

    /** Automatische Stundenberechnung bei vorhandenen Zeiten */
    public function getHoursWorkedAttribute($value): ?float
    {
        // Wenn total_hours gesetzt ist, verwende das (für manuelle Einträge)
        if (isset($this->attributes['total_hours']) && $this->attributes['total_hours'] !== null) {
            return (float)$this->attributes['total_hours'];
        }

        // Sonst verwende hours_worked
        if ($value) {
            return (float)$value;
        }

        // Fallback: Berechne aus Zeiten
        if ($this->clock_in && $this->clock_out) {
            $breakMinutes = $this->break_minutes ?? 0;
            $diff = $this->clock_out->diffInMinutes($this->clock_in) - $breakMinutes;
            return max($diff / 60, 0);
        }

        return null;
    }

    public static function clockOut(Employee $employee): ?self
    {
        $entry = self::where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        if (!$entry) {
            return null;
        }

        $entry->clock_out = now();

        $totalMinutes = $entry->clock_out->diffInMinutes($entry->clock_in);
        $hours = $totalMinutes / 60;

        // Pause anhand BreakRule berechnen
        $breakMinutes = \App\Models\BreakRule::calculateBreakForHours($hours);

        // Berechne Netto-Arbeitszeit und Lohn
        $workMinutes = max(0, $totalMinutes - $breakMinutes);
        $workHours = $workMinutes / 60;
        
        $hourlyRate = $employee->hourly_rate ?? 0;
        $grossWage = $workHours * $hourlyRate;

        $entry->break_minutes = $breakMinutes;
        $entry->hours_worked = $workHours;
        $entry->total_hours = round($workHours, 2);
        $entry->gross_wage = round($grossWage, 2);
        $entry->save();

        return $entry;
    }

    public function finalizeWithBreaks(): void
    {
        $clockIn = $this->clock_in;
        $clockOut = $this->clock_out;

        if ($clockOut->lt($clockIn)) {
            $clockOut->addDay(); // Tageswechsel
        }

        $totalMinutes = $clockOut->diffInMinutes($clockIn);
        $hours = $totalMinutes / 60;

        $breakMinutes = \App\Models\BreakRule::calculateBreakForHours($hours);

        // Berechne Netto-Arbeitszeit und Lohn
        $workMinutes = max(0, $totalMinutes - $breakMinutes);
        $workHours = $workMinutes / 60;
        
        $employee = $this->employee;
        $hourlyRate = $employee->hourly_rate ?? 0;
        $grossWage = $workHours * $hourlyRate;

        $this->break_minutes = $breakMinutes;
        $this->hours_worked = $workHours;
        $this->total_hours = round($workHours, 2);
        $this->gross_wage = round($grossWage, 2);
        $this->save();
    }
}
