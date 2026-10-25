<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HaccpRandomSampleScheduler
{
    public function ensureMonth(int $year, int $month): int
    {
        $settings = $this->settings();
        $samplesPerMonth = max(1, (int)($settings['samples_per_month'] ?? 4));
        $weekdays = array_values(array_unique(array_map('intval', $settings['weekdays'] ?? [1,2,3,4,5,6])));
        $start = Carbon::create($year,$month,1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $existing = DB::table('haccp_food_samples')->whereBetween('scheduled_for',[$start->toDateString(),$end->toDateString()])->count();
        if ($existing >= $samplesPerMonth) return 0;

        $used = DB::table('haccp_food_samples')->whereBetween('scheduled_for',[$start->toDateString(),$end->toDateString()])->pluck('scheduled_for')->map(fn($d)=>Carbon::parse($d)->toDateString())->all();
        $days=[];
        for($d=$start->copy();$d->lte($end);$d->addDay()) {
            if(in_array($d->dayOfWeekIso,$weekdays,true) && !in_array($d->toDateString(),$used,true)) $days[]=$d->toDateString();
        }
        shuffle($days);
        $chosen=array_slice($days,0,min($samplesPerMonth-$existing,count($days)));
        sort($chosen);
        foreach($chosen as $date) DB::table('haccp_food_samples')->insert(['scheduled_for'=>$date,'status'=>'open','created_at'=>now(),'updated_at'=>now()]);
        return count($chosen);
    }

    public function settings(): array
    {
        $value = DB::table('haccp_settings')->where('key','food_sampling')->value('value');
        return $value ? (json_decode($value,true) ?: []) : ['samples_per_month'=>4,'weekdays'=>[1,2,3,4,5,6]];
    }
}
