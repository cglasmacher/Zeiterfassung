<?php
namespace App\Services;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HaccpRandomSampleScheduler
{
    public function ensureMonth(int $year,int $month,int $samplesPerMonth=4): int
    {
        $start=Carbon::create($year,$month,1)->startOfDay();$end=$start->copy()->endOfMonth();
        if(DB::table('haccp_food_samples')->whereBetween('scheduled_for',[$start->toDateString(),$end->toDateString()])->exists())return 0;
        $days=[];for($d=$start->copy();$d->lte($end);$d->addDay()){if($d->isMonday()||$d->isTuesday()||$d->isWednesday()||$d->isThursday()||$d->isFriday()||$d->isSaturday())$days[]=$d->toDateString();}
        shuffle($days);$chosen=array_slice($days,0,min($samplesPerMonth,count($days)));sort($chosen);foreach($chosen as $date)DB::table('haccp_food_samples')->insert(['scheduled_for'=>$date,'status'=>'open','created_at'=>now(),'updated_at'=>now()]);return count($chosen);
    }
}
