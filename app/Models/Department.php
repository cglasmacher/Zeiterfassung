<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    // Viele Mitarbeiter gehören zu vielen Abteilungen
    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_department');
    }

    // Eine Abteilung kann viele Schichten haben
    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }
}
