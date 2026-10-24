<?php

namespace App\Http\Controllers;

use App\Models\User;
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

    public function storeRole(Request $request)
    {
        $data = $request->validate(['name' => ['required','string','max:120','unique:haccp_roles,name'], 'description' => ['nullable','string'], 'active' => ['boolean']]);
        $id = DB::table('haccp_roles')->insertGetId([...$data, 'active' => $data['active'] ?? true, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(DB::table('haccp_roles')->find($id), 201);
    }

    public function updateRole(Request $request, int $id)
    {
        $data = $request->validate(['name' => ['required','string','max:120',Rule::unique('haccp_roles','name')->ignore($id)], 'description' => ['nullable','string'], 'active' => ['boolean']]);
        DB::table('haccp_roles')->where('id',$id)->update([...$data, 'updated_at' => now()]);
        return response()->json(DB::table('haccp_roles')->find($id));
    }

    public function assignRole(Request $request, int $id)
    {
        $data = $request->validate(['user_ids' => ['array'], 'user_ids.*' => ['integer','exists:users,id']]);
        DB::transaction(function () use ($id, $data) {
            DB::table('haccp_role_user')->where('haccp_role_id',$id)->delete();
            foreach ($data['user_ids'] ?? [] as $userId) DB::table('haccp_role_user')->insert(['haccp_role_id'=>$id,'user_id'=>$userId]);
        });
        return response()->noContent();
    }

    public function storeDevice(Request $request)
    {
        $data = $this->deviceData($request);
        $id = DB::table('haccp_devices')->insertGetId([...$data, 'qr_token' => Str::random(40), 'created_at'=>now(), 'updated_at'=>now()]);
        return response()->json(DB::table('haccp_devices')->find($id), 201);
    }

    public function updateDevice(Request $request, int $id)
    {
        DB::table('haccp_devices')->where('id',$id)->update([...$this->deviceData($request), 'updated_at'=>now()]);
        return response()->json(DB::table('haccp_devices')->find($id));
    }

    private function deviceData(Request $request): array
    {
        return $request->validate([
            'name'=>['required','string','max:160'], 'type'=>['nullable','string','max:100'], 'location'=>['nullable','string','max:120'],
            'manufacturer'=>['nullable','string','max:120'], 'model'=>['nullable','string','max:120'], 'serial_number'=>['nullable','string','max:120'],
            'commissioned_at'=>['nullable','date'], 'min_value'=>['nullable','numeric'], 'max_value'=>['nullable','numeric','gte:min_value'],
            'unit'=>['nullable','string','max:20'], 'active'=>['boolean'],
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $data = $this->templateData($request);
        $id = DB::table('haccp_templates')->insertGetId([...$data, 'created_at'=>now(), 'updated_at'=>now()]);
        return response()->json(DB::table('haccp_templates')->find($id), 201);
    }

    public function updateTemplate(Request $request, int $id)
    {
        DB::table('haccp_templates')->where('id',$id)->update([...$this->templateData($request), 'updated_at'=>now()]);
        return response()->json(DB::table('haccp_templates')->find($id));
    }

    private function templateData(Request $request): array
    {
        $data = $request->validate([
            'name'=>['required','string','max:160'], 'category'=>['required','string','max:80'], 'haccp_role_id'=>['nullable','exists:haccp_roles,id'],
            'device_id'=>['nullable','exists:haccp_devices,id'], 'result_type'=>['required',Rule::in(['checkbox','temperature','number','yes_no','text','checklist'])],
            'frequency'=>['required',Rule::in(['daily','weekly','monthly','interval'])], 'schedule'=>['nullable','array'], 'configuration'=>['nullable','array'],
            'requires_corrective_action'=>['boolean'], 'active'=>['boolean'],
        ]);
        $data['schedule'] = isset($data['schedule']) ? json_encode($data['schedule']) : null;
        $data['configuration'] = isset($data['configuration']) ? json_encode($data['configuration']) : null;
        $data['requires_corrective_action'] = $data['requires_corrective_action'] ?? true;
        $data['active'] = $data['active'] ?? true;
        return $data;
    }
}
