<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HaccpComplianceController extends Controller
{
    public function index()
    {
        return response()->json([
            'deviations' => DB::table('haccp_deviations as d')->join('haccp_task_results as r','r.id','=','d.task_result_id')->join('haccp_tasks as t','t.id','=','r.task_id')->leftJoin('haccp_devices as v','v.id','=','t.device_id')->leftJoin('users as u','u.id','=','r.completed_by')->leftJoin('users as ru','ru.id','=','d.resolved_by')->select('d.*','t.title','t.snapshot','v.name as device_name','r.completed_at','r.numeric_value','u.name as detected_by','ru.name as resolved_by_name')->orderByRaw('d.resolved_at IS NULL DESC')->orderByDesc('r.completed_at')->get()->map(function($row){$snapshot=json_decode($row->snapshot?:'{}',true)?:[];$row->criticality=$snapshot['criticality']??'normal';unset($row->snapshot);return $row;}),
            'maintenance_plans' => DB::table('haccp_maintenance_plans as p')->join('haccp_devices as d','d.id','=','p.device_id')->select('p.*','d.name as device_name','d.location')->orderBy('p.next_due_at')->get(),
            'maintenance_records' => DB::table('haccp_maintenance_records as r')->join('haccp_maintenance_plans as p','p.id','=','r.maintenance_plan_id')->join('haccp_devices as d','d.id','=','p.device_id')->leftJoin('users as u','u.id','=','r.completed_by')->select('r.*','p.name as plan_name','d.name as device_name','u.name as completed_by_name')->orderByDesc('r.completed_at')->limit(100)->get(),
            'devices' => DB::table('haccp_devices')->whereNull('deleted_at')->where('active',true)->orderBy('name')->get(['id','name','location']),
        ]);
    }

    public function resolveDeviation(Request $request,int $id)
    {
        $data=$request->validate(['corrective_action'=>'required|string|max:5000']);
        $deviation=DB::table('haccp_deviations')->where('id',$id)->first();abort_unless($deviation,404);abort_if($deviation->resolved_at,409,'Diese Abweichung ist bereits abgeschlossen.');
        DB::table('haccp_deviations')->where('id',$id)->update(['corrective_action'=>$data['corrective_action'],'resolved_by'=>$request->user()->id,'resolved_at'=>now(),'updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }

    public function storeMaintenancePlan(Request $request)
    {
        $this->admin($request);$data=$request->validate(['device_id'=>'required|exists:haccp_devices,id','name'=>'required|string|max:255','frequency'=>'required|in:daily,weekly,monthly,interval','interval_value'=>'required|integer|min:1|max:3650','next_due_at'=>'nullable|date','active'=>'sometimes|boolean']);
        $id=DB::table('haccp_maintenance_plans')->insertGetId([...$data,'active'=>$data['active']??true,'created_at'=>now(),'updated_at'=>now()]);return response()->json(['id'=>$id],201);
    }

    public function updateMaintenancePlan(Request $request,int $id)
    {
        $this->admin($request);abort_unless(DB::table('haccp_maintenance_plans')->where('id',$id)->exists(),404);$data=$request->validate(['device_id'=>'required|exists:haccp_devices,id','name'=>'required|string|max:255','frequency'=>'required|in:daily,weekly,monthly,interval','interval_value'=>'required|integer|min:1|max:3650','next_due_at'=>'nullable|date','active'=>'required|boolean']);DB::table('haccp_maintenance_plans')->where('id',$id)->update([...$data,'updated_at'=>now()]);return response()->json(['ok'=>true]);
    }

    public function completeMaintenance(Request $request,int $id)
    {
        $plan=DB::table('haccp_maintenance_plans')->where('id',$id)->first();abort_unless($plan,404);$data=$request->validate(['completed_at'=>'required|date','provider'=>'nullable|string|max:255','notes'=>'nullable|string|max:5000']);
        $completed=Carbon::parse($data['completed_at']);$next=$this->nextDue($completed,$plan->frequency,(int)$plan->interval_value);
        DB::transaction(function()use($request,$id,$data,$completed,$next){DB::table('haccp_maintenance_records')->insert(['maintenance_plan_id'=>$id,'completed_by'=>$request->user()->id,'completed_at'=>$completed->toDateString(),'provider'=>$data['provider']??null,'notes'=>$data['notes']??null,'created_at'=>now(),'updated_at'=>now()]);DB::table('haccp_maintenance_plans')->where('id',$id)->update(['last_completed_at'=>$completed->toDateString(),'next_due_at'=>$next->toDateString(),'updated_at'=>now()]);});return response()->json(['ok'=>true,'next_due_at'=>$next->toDateString()]);
    }

    private function nextDue(Carbon $date,string $frequency,int $interval): Carbon{return match($frequency){'daily'=>$date->copy()->addDays($interval),'weekly'=>$date->copy()->addWeeks($interval),'monthly'=>$date->copy()->addMonthsNoOverflow($interval),default=>$date->copy()->addDays($interval)};}
    private function admin(Request $request): void{abort_unless($request->user()?->access_role==='admin',403);}
}
