<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_hours',
        'break_minutes',
        'active',
    ];

    /** Gibt die aktuell gültigen Pausenregeln zurück */
    public static function activeRules()
    {
        return self::where('active', true)->orderBy('min_hours')->get();
    }

    /** Berechnet die anzuwendende Pausenzeit für gegebene Arbeitsstunden */
    public static function calculateBreakForHours(float $hours): float
    {
        $rules = self::activeRules();
        $break = 0;
        foreach ($rules as $rule) {
            if ($hours >= $rule->min_hours) {
                $break = $rule->break_minutes;
            }
        }
        return $break;
    }
}
