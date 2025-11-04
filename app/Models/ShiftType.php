<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'default_start',
        'default_end',
        'default_break_minutes',
        'active',
    ];

    // Ein Schichttyp kann mehreren Schichten zugewiesen werden
    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    /** Berechnete Standardstunden */
    public function getDefaultHoursAttribute(): float
    {
        $start = strtotime($this->default_start);
        $end = strtotime($this->default_end);
        $diff = ($end - $start) / 3600 - ($this->default_break_minutes / 60);
        return max($diff, 0);
    }
}
