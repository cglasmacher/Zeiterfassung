<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingsController extends Controller
{
    public function index()
    {
        return response()->json([
            'closed_days' => Setting::get('closed_days', []),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'closed_days' => 'nullable|array',
            'closed_days.*' => 'integer|between:0,6', // 0=Sonntag, 6=Samstag
        ]);

        if (isset($data['closed_days'])) {
            Setting::set('closed_days', $data['closed_days']);
        }

        return response()->json([
            'message' => 'Einstellungen gespeichert',
            'settings' => [
                'closed_days' => Setting::get('closed_days', []),
            ],
        ]);
    }
}