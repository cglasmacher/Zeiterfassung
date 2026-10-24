<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\HaccpTaskGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HaccpController extends Controller
{
    public function overview()
    {
        return response()->json([
            'roles' => DB::table('haccp_roles')->orderBy('name')->get(),
            'devices' => DB::table('haccp_devices')->whereNull('deleted_at')->orderBy('location')->orderBy('name')->get(),
            'templates' => DB::table('haccp_templates')->orderBy('category')->orderBy('name')->get(),
            'users' => User::query()->select('id', 'name', 'email')->orderBy('name')->get(),
            'assignments' => DB::table('haccp_role_user')->get(),
        ]);
    }

    public function today(Request $request, HaccpTaskGenerator $generator)
    {
        $generator->generate();
        $roleIds = DB::table('haccp_role_user')->where('user_id', $request->user()->id)->pluck('haccp_role_id');
        $query = DB::table('haccp_tasks as t')->leftJoin('haccp_devices as d','d.id','=','t.device_id')->leftJoin('haccp_roles as r','r.id','=','t.haccp_role_id')
            ->select('t.*','d.name as device_name','d.location as device_location','d.min_value','d.max_value','d.unit','r.name as role_name')
            ->whereDate('t.due_at', today());
        if ($request->user()->access_role !== 'admin') $query->where(fn($q) => $q->whereNull('t.haccp_role_id')->orWhereIn('t.haccp_role_id', $roleIds));
        return response()->json($query->orderBy('t.due_at')->get()->map(function($task){ $task->snapshot=json_decode($task->snapshot ?: '{}',true); return $task; }));
    }

    public function completeTask(Request $request, int $id)
    {
        $task = DB::table('haccp_tasks')->find($id); abort_unless($task,404); abort_if($task->status === 'completed',422,'Kontrolle wurde bereits abgeschlossen.');
        $snapshot = json_decode($task->snapshot ?: '{}', true); $type = $snapshot['result_type'] ?? 'checkbox';
        $data = $request->validate(['numeric_value'=>['nullable','numeric'],'boolean_value'=>['nullable','boolean'],'text_value'=>['nullable','string'],'payload'=>['nullable','array'],'deviation_description'=>['nullable','string'],'corrective_action'=>['nullable','string']]);
        if (in_array($type,['temperature','number'],true) && !array_key_exists('numeric_value',$data)) abort(422,'Messwert erforderlich.');
        if (in_array($type,['checkbox','yes_no'],true) && !array_key_exists('boolean_value',$data)) abort(422,'Bestätigung erforderlich.');
        $device = $task->device_id ? DB::table('haccp_devices')->find($task->device_id) : null;
        $config = $snapshot['configuration'] ?? []; $min = $config['min_value'] ?? $device?->min_value; $max = $config['max_value'] ?? $device?->max_value;
        $within = null; if (isset($data['numeric_value']) && ($min !== null || $max !== null)) $within = ($min === null || $data['numeric_value'] >= $min) && ($max === null || $data['numeric_value'] <= $max);
        if ($type === 'yes_no' && isset($data['boolean_value'])) $within = (bool)$data['boolean_value'];
        if ($within === false && (($snapshot['requires_corrective_action'] ?? true)) && empty($data['corrective_action'])) abort(422,'Bei einer Abweichung ist eine Korrekturmaßnahme erforderlich.');
        DB::transaction(function() use($request,$task,$data,$within){
            $resultId=DB::table('haccp_task_results')->insertGetId(['task_id'=>$task->id,'completed_by'=>$request->user()->id,'completed_at'=>now(),'numeric_value'=>$data['numeric_value']??null,'boolean_value'=>$data['boolean_value']??null,'text_value'=>$data['text_value']??null,'payload'=>isset($data['payload'])?json_encode($data['payload']):null,'within_limits'=>$within,'created_at'=>now(),'updated_at'=>now()]);
            if($within===false) DB::table('haccp_deviations')->insert(['task_result_id'=>$resultId,'description'=>$data['deviation_description']??'Grenzwert bzw. Sollzustand nicht eingehalten.','corrective_action'=>$data['corrective_action']??null,'created_at'=>now(),'updated_at'=>now()]);
            DB::table('haccp_tasks')->where('id',$task->id)->update(['status'=>$within===false?'deviation':'completed','updated_at'=>now()]);
        });
        return response()->json(['ok'=>true,'within_limits'=>$within]);
    }

    public function storeRole(Request $request){$data=$request->validate(['name'=>['required','string','max:120','unique:haccp_roles,name'],'description'=>['nullable','string'],'active'=>['boolean']]);$id=DB::table('haccp_roles')->insertGetId([...$data,'active'=>$data['active']??true,'created_at'=>now(),'updated_at'=>now()]);return response()->json(DB::table('haccp_roles')->find($id),201);}
    public function updateRole(Request $request,int $id){$data=$request->validate(['name'=>['required','string','max:120',Rule::unique('haccp_roles','name')->ignore($id)],'description'=>['nullable','string'],'active'=>['boolean']]);DB::table('haccp_roles')->where('id',$id)->update([...$data,'updated_at'=>now()]);return response()->json(DB::table('haccp_roles')->find($id));}
    public function assignRole(Request $request,int $id){$data=$request->validate(['user_ids'=>['array'],'user_ids.*'=>['integer','exists:users,id']]);DB::transaction(function()use($id,$data){DB::table('haccp_role_user')->where('haccp_role_id',$id)->delete();foreach($data['user_ids']??[] as $userId)DB::table('haccp_role_user')->insert(['haccp_role_id'=>$id,'user_id'=>$userId]);});return response()->noContent();}
    public function storeDevice(Request $request){$data=$this->deviceData($request);$id=DB::table('haccp_devices')->insertGetId([...$data,'qr_token'=>Str::random(40),'created_at'=>now(),'updated_at'=>now()]);return response()->json(DB::table('haccp_devices')->find($id),201);}
    public function updateDevice(Request $request,int $id){DB::table('haccp_devices')->where('id',$id)->update([...$this->deviceData($request),'updated_at'=>now()]);return response()->json(DB::table('haccp_devices')->find($id));}
    private function deviceData(Request $request):array{return $request->validate(['name'=>['required','string','max:160'],'type'=>['nullable','string','max:100'],'location'=>['nullable','string','max:120'],'manufacturer'=>['nullable','string','max:120'],'model'=>['nullable','string','max:120'],'serial_number'=>['nullable','string','max:120'],'commissioned_at'=>['nullable','date'],'min_value'=>['nullable','numeric'],'max_value'=>['nullable','numeric','gte:min_value'],'unit'=>['nullable','string','max:20'],'active'=>['boolean']]);}
    public function storeTemplate(Request $request){$data=$this->templateData($request);$id=DB::table('haccp_templates')->insertGetId([...$data,'created_at'=>now(),'updated_at'=>now()]);return response()->json(DB::table('haccp_templates')->find($id),201);}
    public function updateTemplate(Request $request,int $id){DB::table('haccp_templates')->where('id',$id)->update([...$this->templateData($request),'updated_at'=>now()]);return response()->json(DB::table('haccp_templates')->find($id));}
    private function templateData(Request $request):array{$data=$request->validate(['name'=>['required','string','max:160'],'category'=>['required','string','max:80'],'haccp_role_id'=>['nullable','exists:haccp_roles,id'],'device_id'=>['nullable','exists:haccp_devices,id'],'result_type'=>['required',Rule::in(['checkbox','temperature','number','yes_no','text','checklist'])],'frequency'=>['required',Rule::in(['daily','weekly','monthly','interval'])],'schedule'=>['nullable','array'],'configuration'=>['nullable','array'],'requires_corrective_action'=>['boolean'],'active'=>['boolean']]);$data['schedule']=isset($data['schedule'])?json_encode($data['schedule']):null;$data['configuration']=isset($data['configuration'])?json_encode($data['configuration']):null;$data['requires_corrective_action']=$data['requires_corrective_action']??true;$data['active']=$data['active']??true;return $data;}
}
