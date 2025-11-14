<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'position',
        'employment_type',
        'hourly_rate',
        'max_monthly_hours',
        'rfid_tag',
        'active',
    ];

    /** Beziehungen */

    // Ein Mitarbeiter hat viele Zeitstempel
    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    // Ein Mitarbeiter hat viele Schichten
    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }

    // Ein Mitarbeiter gehört zu einer oder mehreren Abteilungen
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'employee_department');
    }

    /** Accessor-Beispiel */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
