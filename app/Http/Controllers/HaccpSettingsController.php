<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HaccpSettingsController extends Controller
{
    public function show(Request $request)
    {
        $this->admin($request);
        $rows = DB::table('haccp_settings')->get();
        return response()->json($rows->mapWithKeys(fn($row)=>[$row->key => json_decode($row->value ?: '{}', true)]));
    }

    public function update(Request $request)
    {
        $this->admin($request);
        $data = $request->validate([
            'food_sampling.samples_per_month' => ['required','integer','min:1','max:31'],
            'food_sampling.weekdays' => ['required','array','min:1'],
            'food_sampling.weekdays.*' => ['integer','between:1,7','distinct'],
            'food_sampling.default_min_temperature' => ['nullable','numeric'],
            'food_sampling.default_max_temperature' => ['nullable','numeric','gte:food_sampling.default_min_temperature'],
        ]);
        DB::table('haccp_settings')->updateOrInsert(
            ['key'=>'food_sampling'],
            ['value'=>json_encode($data['food_sampling']),'updated_at'=>now(),'created_at'=>now()]
        );
        return response()->json(['food_sampling'=>$data['food_sampling']]);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->access_role === 'admin', 403);
    }
}
