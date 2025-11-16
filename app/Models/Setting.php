<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Hole einen Einstellungswert
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        // Versuche JSON zu dekodieren
        $decoded = json_decode($setting->value, true);
        return $decoded !== null ? $decoded : $setting->value;
    }

    /**
     * Setze einen Einstellungswert
     */
    public static function set($key, $value)
    {
        // Encode arrays/objects als JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}