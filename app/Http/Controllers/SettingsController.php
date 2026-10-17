<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return response()->json([
            'closed_days' => Setting::get('closed_days', []),
            'cash_float_target' => Setting::get('cash_float_target', '250.00'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cash_float_target' => 'sometimes|required|numeric|min:0|max:999999',
            'closed_days' => 'nullable|array',
            'closed_days.*' => 'integer|between:0,6', // 0=Sonntag, 6=Samstag
        ]);

        if (isset($data['cash_float_target'])) {
            Setting::set('cash_float_target', number_format((float) $data['cash_float_target'], 2, '.', ''));
        }

        if (isset($data['closed_days'])) {
            Setting::set('closed_days', $data['closed_days']);
        }

        return response()->json([
            'message' => 'Einstellungen gespeichert',
            'settings' => [
                'closed_days' => Setting::get('closed_days', []),
                'cash_float_target' => Setting::get('cash_float_target', '250.00'),
            ],
        ]);
    }
}
