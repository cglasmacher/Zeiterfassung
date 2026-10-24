<?php

namespace App\Http\Controllers;

use App\Services\HaccpRandomSampleScheduler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HaccpExtendedController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->user()?->access_role === 'admin', 403);
    }

    public function index(HaccpRandomSampleScheduler $scheduler)
    {
        $scheduler->ensureMonth((int) now()->format('Y'), (int) now()->format('n'));

        return response()->json([
            'device_types' => DB::table('haccp_device_types')->orderBy('name')->get(),
            'cleaning_plans' => DB::table('haccp_cleaning_plans')->orderBy('name')->get()->map(function ($plan) {
                $plan->schedule = json_decode($plan->schedule ?: '{}', true); return $plan;
            }),
            'cleaning_today' => DB::table('haccp_cleaning_records as cr')->join('haccp_cleaning_plans as cp','cp.id','=','cr.cleaning_plan_id')->leftJoin('haccp_roles as r','r.id','=','cp.haccp_role_id')->whereDate('cr.due_date',today())->select('cr.*','cp.name','cp.location','cp.activity','cp.cleaning_agent','cp.disinfectant','r.name as role_name')->get(),
            'food_samples_today' => DB::table('haccp_food_samples')->whereDate('scheduled_for',today())->get(),
            'recent_goods_receipts' => DB::table('haccp_goods_receipts')->orderByDesc('completed_at')->limit(10)->get(),
            'recent_retention_samples' => DB::table('haccp_retention_samples')->orderByDesc('sampled_at')->limit(10)->get(),
        ]);
    }

    public function storeDeviceType(Request $request)
    {
        $this->admin($request);
        $data = $this->deviceTypeData($request);
        $id = DB::table('haccp_device_types')->insertGetId([...$data,'active'=>$data['active']??true,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(DB::table('haccp_device_types')->find($id), 201);
    }

    public function updateDeviceType(Request $request, int $id)
    {
        $this->admin($request);
        DB::table('haccp_device_types')->where('id',$id)->update([...$this->deviceTypeData($request,$id),'updated_at'=>now()]);
        return response()->json(DB::table('haccp_device_types')->find($id));
    }

    private function deviceTypeData(Request $request, ?int $id=null): array
    {
        return $request->validate([
            'name'=>['required','string','max:120',Rule::unique('haccp_device_types','name')->ignore($id)],
            'unit'=>'nullable|string|max:20','default_min_value'=>'nullable|numeric','default_max_value'=>'nullable|numeric|gte:default_min_value',
            'default_frequency'=>['nullable',Rule::in(['daily','weekly','monthly','interval'])], 'default_interval_value'=>'nullable|integer|min:1','active'=>'boolean',
        ]);
    }

    public function goodsReceipt(Request $request)
    {
        $data=$request->validate(['supplier'=>'nullable|string|max:160','item'=>'required|string|max:255','visual_ok'=>'required|boolean','olfactory_ok'=>'required|boolean','supplier_docs_complete'=>'required|boolean','temperature'=>'nullable|numeric','notes'=>'nullable|string']);
        $id=DB::table('haccp_goods_receipts')->insertGetId([...$data,'completed_by'=>$request->user()->id,'completed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['id'=>$id],201);
    }

    public function retentionSample(Request $request)
    {
        $data=$request->validate(['product'=>'required|string|max:255','batch'=>'nullable|string|max:120','produced_at'=>'nullable|date','sampled_at'=>'required|date','label'=>'nullable|string|max:160','storage_location'=>'nullable|string|max:160','dispose_on'=>'nullable|date|after_or_equal:sampled_at','notes'=>'nullable|string']);
        $id=DB::table('haccp_retention_samples')->insertGetId([...$data,'recorded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['id'=>$id],201);
    }

    public function completeFoodSample(Request $request,int $id)
    {
        $data=$request->validate(['dish'=>'required|string|max:255','core_temperature'=>'required|numeric','sensory_ok'=>'required|boolean','notes'=>'nullable|string']);
        $updated=DB::table('haccp_food_samples')->where('id',$id)->where('status','open')->update([...$data,'completed_by'=>$request->user()->id,'completed_at'=>now(),'status'=>'completed','updated_at'=>now()]);
        abort_unless($updated,422,'Stichprobe ist nicht mehr offen.');
        return response()->noContent();
    }

    public function storeCleaningPlan(Request $request)
    {
        $this->admin($request); $data=$this->cleaningData($request);
        $id=DB::table('haccp_cleaning_plans')->insertGetId([...$data,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(DB::table('haccp_cleaning_plans')->find($id),201);
    }

    public function updateCleaningPlan(Request $request,int $id)
    {
        $this->admin($request); DB::table('haccp_cleaning_plans')->where('id',$id)->update([...$this->cleaningData($request),'updated_at'=>now()]);
        return response()->json(DB::table('haccp_cleaning_plans')->find($id));
    }

    private function cleaningData(Request $request): array
    {
        $data=$request->validate(['name'=>'required|string|max:160','location'=>'nullable|string|max:160','activity'=>'required|string','cleaning_agent'=>'nullable|string|max:160','disinfectant'=>'nullable|string|max:160','haccp_role_id'=>'nullable|exists:haccp_roles,id','frequency'=>['required',Rule::in(['daily','weekly','monthly','interval'])],'interval_value'=>'nullable|integer|min:1','schedule'=>'nullable|array','active'=>'boolean']);
        $data['schedule']=isset($data['schedule'])?json_encode($data['schedule']):null; $data['interval_value']=$data['interval_value']??1; $data['active']=$data['active']??true; return $data;
    }

    public function completeCleaning(Request $request,int $id)
    {
        $record=DB::table('haccp_cleaning_records')->find($id); abort_unless($record,404); abort_if($record->completed_at,422,'Reinigung bereits dokumentiert.');
        $data=$request->validate(['notes'=>'nullable|string']); DB::table('haccp_cleaning_records')->where('id',$id)->update(['completed_by'=>$request->user()->id,'completed_at'=>now(),'notes'=>$data['notes']??null,'updated_at'=>now()]);
        return response()->noContent();
    }
}
