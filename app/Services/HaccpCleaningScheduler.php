<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HaccpCleaningScheduler
{
    public function generate(?Carbon $date=null): int
    {
        $date=($date??today())->copy()->startOfDay();$count=0;
        foreach(DB::table('haccp_cleaning_plans')->where('active',true)->get() as $plan){
            if(!$this->isDue($plan,$date)) continue;
            $inserted=DB::table('haccp_cleaning_records')->insertOrIgnore(['cleaning_plan_id'=>$plan->id,'due_date'=>$date->toDateString(),'created_at'=>now(),'updated_at'=>now()]);
            $count+=(int)$inserted;
        }
        return $count;
    }

    private function isDue(object $plan, Carbon $date): bool
    {
        $schedule=json_decode($plan->schedule?:'{}',true)?:[];
        $interval=max(1,(int)($plan->interval_value??1));
        $created=Carbon::parse($plan->created_at)->startOfDay();
        if($date->lt($created))return false;
        return match($plan->frequency){
            'daily'=>true,
            'weekly'=>in_array($date->dayOfWeekIso,array_map('intval',$schedule['weekdays']??[1]),true),
            'monthly'=>$date->day===min((int)($schedule['day']??1),$date->daysInMonth),
            'interval'=>$created->diffInDays($date)%$interval===0,
            default=>false,
        };
    }
}
