<?php

namespace App\Services\Haccp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HaccpMonthlyDocumentation
{
    public function build(int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $tasks = DB::table('haccp_tasks as t')
            ->join('haccp_templates as p', 'p.id', '=', 't.template_id')
            ->leftJoin('haccp_devices as d', 'd.id', '=', 't.device_id')
            ->leftJoin('haccp_device_types as dt', 'dt.id', '=', 'd.device_type_id')
            ->whereBetween('t.due_at', [$from, $to])
            ->select('t.*', 'p.category', 'p.result_type', 'p.name as template_name', 'p.criticality', 'p.version as template_version', 'd.name as device_name', 'd.location', 'd.min_value', 'd.max_value', 'd.unit', 'dt.name as device_type_name')
            ->orderBy('t.due_at')->get();

        $taskIds = $tasks->pluck('id');
        $results = $taskIds->isEmpty() ? collect() : DB::table('haccp_task_results as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.completed_by')
            ->whereIn('r.task_id', $taskIds)
            ->select('r.*', 'u.name as completed_by_name')->get()->keyBy('task_id');

        $resultIds = $results->pluck('id');
        $deviations = $resultIds->isEmpty() ? collect() : DB::table('haccp_deviations as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.resolved_by')
            ->whereIn('d.task_result_id', $resultIds)
            ->select('d.*', 'u.name as resolved_by_name')->get()->keyBy('task_result_id');

        $temperature = [];
        foreach ($tasks->where('result_type', 'temperature') as $task) {
            $result = $results->get($task->id); $day = Carbon::parse($task->due_at)->day; $device = $task->device_name ?: $task->title;
            $temperature[$device]['meta'] ??= ['type'=>$task->device_type_name,'location'=>$task->location,'min'=>$task->min_value,'max'=>$task->max_value,'unit'=>$task->unit ?: '°C'];
            $temperature[$device]['days'][$day][] = ['value'=>$result?->numeric_value,'within_limits'=>$result?->within_limits,'completed_at'=>$result?->completed_at,'completed_by'=>$result?->completed_by_name,'missing'=>!$result,'deviation_id'=>$result && $deviations->has($result->id) ? $deviations->get($result->id)->id : null];
        }

        $deviationRows = [];
        foreach ($deviations as $deviation) {
            $result=$results->firstWhere('id',$deviation->task_result_id); $task=$result?$tasks->firstWhere('id',$result->task_id):null; if(!$task)continue;
            $snapshot=json_decode($task->snapshot ?: '{}',true) ?: [];
            $deviationRows[]=['id'=>$deviation->id,'date'=>Carbon::parse($result->completed_at)->toDateString(),'control'=>$task->title,'device'=>$task->device_name,'criticality'=>$snapshot['criticality'] ?? $task->criticality ?? 'normal','value'=>$result->numeric_value,'description'=>$deviation->description,'corrective_action'=>$deviation->corrective_action,'resolved_at'=>$deviation->resolved_at,'resolved_by'=>$deviation->resolved_by_name];
        }

        $goodsReceipts=DB::table('haccp_goods_receipts as g')->leftJoin('users as u','u.id','=','g.completed_by')->whereBetween('g.completed_at',[$from,$to])->select('g.*','u.name as completed_by_name')->orderBy('g.completed_at')->get();
        $foodSamples=DB::table('haccp_food_samples as f')->leftJoin('users as u','u.id','=','f.completed_by')->whereBetween('f.scheduled_for',[$from->toDateString(),$to->toDateString()])->select('f.*','u.name as completed_by_name')->orderBy('f.scheduled_for')->get();
        $retentionSamples=DB::table('haccp_retention_samples as r')->leftJoin('users as u','u.id','=','r.recorded_by')->whereBetween('r.sampled_at',[$from,$to])->select('r.*','u.name as recorded_by_name')->orderBy('r.sampled_at')->get();
        $cleaning=DB::table('haccp_cleaning_records as r')->join('haccp_cleaning_plans as p','p.id','=','r.cleaning_plan_id')->leftJoin('haccp_roles as hr','hr.id','=','p.haccp_role_id')->leftJoin('users as u','u.id','=','r.completed_by')->whereBetween('r.due_date',[$from->toDateString(),$to->toDateString()])->select('r.*','p.name as plan_name','p.location','p.activity','p.cleaning_agent','p.disinfectant','hr.name as role_name','u.name as completed_by_name')->orderBy('r.due_date')->get();

        $completed=$results->count(); $total=$tasks->count(); $missing=max(0,$total-$completed);
        $cleaningMissing=$cleaning->whereNull('completed_at')->count(); $foodMissing=$foodSamples->where('status','open')->count();
        $ccpOpen=collect($deviationRows)->where('criticality','critical')->whereNull('resolved_at')->count();

        return [
            'period'=>['year'=>$year,'month'=>$month,'from'=>$from->toDateString(),'to'=>$to->toDateString(),'days'=>$to->day],
            'statistics'=>['total'=>$total,'completed'=>$completed,'missing'=>$missing,'completion_rate'=>$total?round($completed/$total*100,1):100,'deviations'=>count($deviationRows),'open_deviations'=>collect($deviationRows)->whereNull('resolved_at')->count(),'open_ccp_deviations'=>$ccpOpen,'goods_receipts'=>$goodsReceipts->count(),'food_samples'=>$foodSamples->count(),'missing_food_samples'=>$foodMissing,'retention_samples'=>$retentionSamples->count(),'cleaning_due'=>$cleaning->count(),'cleaning_completed'=>$cleaning->whereNotNull('completed_at')->count(),'cleaning_missing'=>$cleaningMissing],
            'temperature_matrix'=>$temperature,'deviations'=>$deviationRows,
            'missing_controls'=>$tasks->filter(fn($task)=>!$results->has($task->id))->map(fn($task)=>['id'=>$task->id,'due_at'=>$task->due_at,'title'=>$task->title,'device'=>$task->device_name,'category'=>$task->category,'criticality'=>$task->criticality])->values()->all(),
            'goods_receipts'=>$goodsReceipts->values()->all(),'food_samples'=>$foodSamples->values()->all(),'retention_samples'=>$retentionSamples->values()->all(),'cleaning_records'=>$cleaning->values()->all(),
        ];
    }
}
