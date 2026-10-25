<?php

namespace App\Http\Controllers;

use App\Services\HaccpRandomSampleScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HaccpExtendedController extends Controller
{
    private function admin(Request $request): void { abort_unless($request->user()?->access_role === 'admin',403); }
    public function index(HaccpRandomSampleScheduler $scheduler)
    {
        $scheduler->ensureMonth((int)now()->format('Y'),(int)now()->format('n'));
        return response()->json([
            'device_types'=>DB::table('haccp_device_types')->orderBy('name')->get(),
            'cleaning_plans'=>DB::table('haccp_cleaning_plans')->orderBy('name')->orderByDesc('version')->get()->map(function($p){$p->schedule=json_decode($p->schedule?:'{}',true);return $p;}),
            'cleaning_records'=>DB::table('haccp_cleaning_records as cr')->join('haccp_cleaning_plans as cp','cp.id','=','cr.cleaning_plan_id')->leftJoin('haccp_roles as r','r.id','=','cp.haccp_role_id')->whereDate('cr.due_date',today())->whereNull('cr.completed_at')->select('cr.*','cp.name as plan_name','cp.location','cp.activity','cp.cleaning_agent','cp.disinfectant','r.name as role_name')->get(),
            'food_samples'=>DB::table('haccp_food_samples')->whereDate('scheduled_for',today())->where('status','open')->get(),
            'goods_receipts'=>DB::table('haccp_goods_receipts')->orderByDesc('completed_at')->limit(10)->get(),
            'retention_samples'=>DB::table('haccp_retention_samples')->orderByDesc('sampled_at')->limit(10)->get(),
        ]);
    }
    public function storeDeviceType(Request $r){$this->admin($r);$d=$this->deviceTypeData($r);$id=DB::table('haccp_device_types')->insertGetId([...$d,'active'=>$d['active']??true,'created_at'=>now(),'updated_at'=>now()]);return response()->json(DB::table('haccp_device_types')->find($id),201);}
    public function updateDeviceType(Request $r,int $id){$this->admin($r);abort_unless(DB::table('haccp_device_types')->where('id',$id)->exists(),404);DB::table('haccp_device_types')->where('id',$id)->update([...$this->deviceTypeData($r,$id),'updated_at'=>now()]);return response()->json(DB::table('haccp_device_types')->find($id));}
    private function deviceTypeData(Request $r,?int $id=null):array{return $r->validate(['name'=>['required','string','max:120',Rule::unique('haccp_device_types','name')->ignore($id)],'unit'=>'nullable|string|max:20','default_min_value'=>'nullable|numeric','default_max_value'=>'nullable|numeric|gte:default_min_value','default_frequency'=>['nullable',Rule::in(['daily','weekly','monthly','interval'])],'default_interval_value'=>'nullable|integer|min:1','active'=>'boolean']);}
    public function goodsReceipt(Request $r){$d=$r->validate(['supplier'=>'nullable|string|max:160','item'=>'required|string|max:255','visual_ok'=>'required|boolean','olfactory_ok'=>'required|boolean','supplier_docs_complete'=>'required|boolean','temperature'=>'nullable|numeric','notes'=>'nullable|string']);$id=DB::table('haccp_goods_receipts')->insertGetId([...$d,'completed_by'=>$r->user()->id,'completed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);return response()->json(['id'=>$id],201);}
    public function retentionSample(Request $r){$d=$r->validate(['product'=>'required|string|max:255','batch'=>'nullable|string|max:120','produced_at'=>'nullable|date','sampled_at'=>'required|date','label'=>'nullable|string|max:160','storage_location'=>'nullable|string|max:160','dispose_on'=>'nullable|date','notes'=>'nullable|string']);$id=DB::table('haccp_retention_samples')->insertGetId([...$d,'recorded_by'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);return response()->json(['id'=>$id],201);}
    public function completeFoodSample(Request $r,int $id){$d=$r->validate(['dish'=>'required|string|max:255','core_temperature'=>'required|numeric','sensory_ok'=>'required|boolean','notes'=>'nullable|string']);$updated=DB::table('haccp_food_samples')->where('id',$id)->where('status','open')->update([...$d,'completed_by'=>$r->user()->id,'completed_at'=>now(),'status'=>'completed','updated_at'=>now()]);abort_unless($updated,422,'Stichprobe ist nicht mehr offen.');return response()->noContent();}
    public function storeCleaningPlan(Request $r){$this->admin($r);$d=$this->cleaningData($r);$id=DB::table('haccp_cleaning_plans')->insertGetId([...$d,'version'=>1,'created_at'=>now(),'updated_at'=>now()]);return response()->json(DB::table('haccp_cleaning_plans')->find($id),201);}
    public function updateCleaningPlan(Request $r,int $id)
    {
        $this->admin($r);$plan=DB::table('haccp_cleaning_plans')->find($id);abort_unless($plan,404);$data=$this->cleaningData($r);
        if(!$plan->locked_at){DB::table('haccp_cleaning_plans')->where('id',$id)->update([...$data,'updated_at'=>now()]);return response()->json(DB::table('haccp_cleaning_plans')->find($id));}
        $newId=DB::transaction(function()use($plan,$data){
            DB::table('haccp_cleaning_plans')->where('id',$plan->id)->update(['active'=>false,'updated_at'=>now()]);
            return DB::table('haccp_cleaning_plans')->insertGetId([...$data,'version'=>(int)$plan->version+1,'supersedes_cleaning_plan_id'=>$plan->id,'locked_at'=>null,'created_at'=>now(),'updated_at'=>now()]);
        });
        return response()->json(DB::table('haccp_cleaning_plans')->find($newId),201);
    }
    private function cleaningData(Request $r):array{$d=$r->validate(['name'=>'required|string|max:160','location'=>'nullable|string|max:160','activity'=>'required|string','cleaning_agent'=>'nullable|string|max:160','disinfectant'=>'nullable|string|max:160','haccp_role_id'=>'nullable|exists:haccp_roles,id','frequency'=>['required',Rule::in(['daily','weekly','monthly','interval'])],'interval_value'=>'nullable|integer|min:1','schedule'=>'nullable|array','active'=>'boolean']);$d['schedule']=isset($d['schedule'])?json_encode($d['schedule']):null;$d['interval_value']=$d['interval_value']??1;$d['active']=$d['active']??true;return $d;}
    public function completeCleaning(Request $r,int $id){$rec=DB::table('haccp_cleaning_records')->find($id);abort_unless($rec,404);abort_if($rec->completed_at,422,'Reinigung bereits dokumentiert.');$d=$r->validate(['notes'=>'nullable|string']);DB::table('haccp_cleaning_records')->where('id',$id)->update(['completed_by'=>$r->user()->id,'completed_at'=>now(),'notes'=>$d['notes']??null,'updated_at'=>now()]);return response()->noContent();}
}
