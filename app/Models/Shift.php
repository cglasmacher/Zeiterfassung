<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'department_id',
        'shift_type_id',
        'shift_date',
        'start_time',
        'end_time',
        'planned_hours',
        'status',
    ];

    /** Beziehungen */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function shiftType()
    {
        return $this->belongsTo(ShiftType::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** Automatische geplante Stundenberechnung (Fallback auf Schichttyp) */
    public function getPlannedHoursAttribute($value): float
    {
        if ($value) {
            return $value;
        }

        if ($this->start_time && $this->end_time) {
            $start = Carbon::parse($this->start_time);
            $end   = Carbon::parse($this->end_time);
            return $end->diffInMinutes($start) / 60;
        }

        return $this->shiftType?->default_hours ?? 0;
    }
}
