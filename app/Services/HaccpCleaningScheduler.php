<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HaccpCleaningScheduler
{
    public function generate(?Carbon $date=null): int
    {
        $date=($date??today())->copy()->startOfDay(); $count=0;
        foreach(DB::table('haccp_cleaning_plans')->where('active',true)->get() as $plan){
            if(!$this->isDue($plan,$date)) continue;
            $exists=DB::table('haccp_cleaning_records')->where('cleaning_plan_id',$plan->id)->whereDate('due_date',$date)->exists();
            if(!$exists){DB::table('haccp_cleaning_records')->insert(['cleaning_plan_id'=>$plan->id,'due_date'=>$date->toDateString(),'created_at'=>now(),'updated_at'=>now()]);$count++;}
        }
        return $count;
    }

    private function isDue(object $plan, Carbon $date): bool
    {
        $schedule=json_decode($plan->schedule?:'{}',true); $interval=max(1,(int)($plan->interval_value??1));
        return match($plan->frequency){
            'daily'=>true,
            'weekly'=>in_array($date->dayOfWeekIso,$schedule['weekdays']??[1],true),
            'monthly'=>$date->day===(int)($schedule['day']??1),
            'interval'=>$date->diffInDays(Carbon::parse($plan->created_at)->startOfDay())%$interval===0,
            default=>false,
        };
    }
}
