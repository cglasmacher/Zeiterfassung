<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HaccpCorrectionController extends Controller
{
    private const SOURCES = [
        'task_result' => ['table'=>'haccp_task_results','date'=>'completed_at','fields'=>['numeric_value','boolean_value','text_value','payload']],
        'goods_receipt' => ['table'=>'haccp_goods_receipts','date'=>'completed_at','fields'=>['supplier','item','temperature','min_temperature','max_temperature','visual_ok','olfactory_ok','supplier_docs_complete','note']],
        'food_sample' => ['table'=>'haccp_food_samples','date'=>'completed_at','fields'=>['dish','core_temperature','min_temperature','max_temperature','sensory_ok','note']],
        'retention_sample' => ['table'=>'haccp_retention_samples','date'=>'sampled_at','fields'=>['product','batch','label','storage_location','dispose_on','note']],
        'cleaning_record' => ['table'=>'haccp_cleaning_records','date'=>'completed_at','fields'=>['note']],
        'maintenance_record' => ['table'=>'haccp_maintenance_records','date'=>'completed_at','fields'=>['provider','note']],
    ];

    public function index(Request $request)
    {
        $this->admin($request);
        $query=DB::table('haccp_corrections as c')->leftJoin('users as u','u.id','=','c.corrected_by')->leftJoin('haccp_monthly_closings as m','m.id','=','c.monthly_closing_id')->select('c.*','u.name as corrected_by_name','m.document_number');
        if($request->filled('source_type'))$query->where('c.source_type',$request->string('source_type'));
        if($request->filled('source_id'))$query->where('c.source_id',$request->integer('source_id'));
        if($request->filled('year'))$query->whereYear('c.corrected_at',$request->integer('year'));
        if($request->filled('month'))$query->whereMonth('c.corrected_at',$request->integer('month'));
        return response()->json($query->orderByDesc('c.corrected_at')->limit(250)->get()->map(fn($r)=>$this->decode($r)));
    }

    public function show(Request $request,string $type,int $id)
    {
        $this->admin($request);[$definition,$original,$effective]=$this->record($type,$id);
        return response()->json(['source_type'=>$type,'source_id'=>$id,'original'=>$original,'effective'=>$effective,'editable_fields'=>$definition['fields'],'corrections'=>$this->history($type,$id)]);
    }

    public function store(Request $request,string $type,int $id)
    {
        $this->admin($request);[$definition,$original,$effective]=$this->record($type,$id);
        $data=$request->validate(['reason'=>['required','string','min:5','max:2000'],'changes'=>['required','array','min:1']]);
        $allowed=array_flip($definition['fields']);$changes=[];
        foreach($data['changes'] as $field=>$value){abort_unless(isset($allowed[$field]),422,'Feld '.$field.' darf nicht korrigiert werden.');if($field==='payload'&&is_array($value))$value=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(in_array($field,['boolean_value','visual_ok','olfactory_ok','supplier_docs_complete','sensory_ok'],true)&&$value!==null)$value=(bool)$value;$changes[$field]=$value;}
        abort_if(!$changes,422,'Keine Korrektur angegeben.');$after=(array)$effective;$fieldChanges=[];foreach($changes as $field=>$value){$before=$after[$field]??null;if($this->same($before,$value))continue;$fieldChanges[$field]=['before'=>$before,'after'=>$value];$after[$field]=$value;}abort_if(!$fieldChanges,422,'Die Korrektur enthält keine Änderung.');
        $recordDate=$original->{$definition['date']}??null;$closing=$recordDate?$this->closingFor($recordDate):null;$now=now();
        $correctionId=DB::table('haccp_corrections')->insertGetId(['source_type'=>$type,'source_id'=>$id,'corrected_by'=>$request->user()->id,'monthly_closing_id'=>$closing?->id,'reason'=>$data['reason'],'before_snapshot'=>json_encode((array)$effective,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'after_snapshot'=>json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'field_changes'=>json_encode($fieldChanges,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'corrected_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
        return response()->json(['id'=>$correctionId,'closed_month'=>(bool)$closing,'document_number'=>$closing?->document_number,'effective'=>$after,'message'=>$closing?'Korrektur als Nachtrag erfasst. Das archivierte Monats-PDF bleibt unverändert.':'Korrektur revisionssicher erfasst. Der Originaldatensatz bleibt unverändert.'],201);
    }

    public function month(Request $request,int $year,int $month)
    {
        $this->admin($request);abort_unless($month>=1&&$month<=12,404);$closing=DB::table('haccp_monthly_closings')->where(['year'=>$year,'month'=>$month])->first();
        $query=DB::table('haccp_corrections as c')->leftJoin('users as u','u.id','=','c.corrected_by')->select('c.*','u.name as corrected_by_name');
        if($closing)$query->where('c.monthly_closing_id',$closing->id);else{$start=sprintf('%04d-%02d-01',$year,$month);$end=date('Y-m-t',strtotime($start));$query->whereBetween('c.corrected_at',[$start.' 00:00:00',$end.' 23:59:59']);}
        return response()->json(['closing'=>$closing,'corrections'=>$query->orderBy('c.corrected_at')->get()->map(fn($r)=>$this->decode($r))]);
    }

    private function record(string $type,int $id): array
    {
        abort_unless(isset(self::SOURCES[$type]),404);$definition=self::SOURCES[$type];$original=DB::table($definition['table'])->find($id);abort_unless($original,404,'HACCP-Nachweis nicht gefunden.');$effective=(array)$original;$last=DB::table('haccp_corrections')->where(['source_type'=>$type,'source_id'=>$id])->orderByDesc('id')->first();if($last)$effective=json_decode($last->after_snapshot,true)?:$effective;return [$definition,$original,(object)$effective];
    }
    private function history(string $type,int $id){return DB::table('haccp_corrections as c')->leftJoin('users as u','u.id','=','c.corrected_by')->leftJoin('haccp_monthly_closings as m','m.id','=','c.monthly_closing_id')->where(['c.source_type'=>$type,'c.source_id'=>$id])->select('c.*','u.name as corrected_by_name','m.document_number')->orderBy('c.id')->get()->map(fn($r)=>$this->decode($r));}
    private function decode(object $row):object{foreach(['before_snapshot','after_snapshot','field_changes'] as $field)$row->{$field}=json_decode($row->{$field}?:'{}',true);return $row;}
    private function closingFor(string $date):?object{$ts=strtotime($date);return DB::table('haccp_monthly_closings')->where(['year'=>(int)date('Y',$ts),'month'=>(int)date('n',$ts)])->first();}
    private function same($a,$b):bool{return json_encode($a,JSON_PRESERVE_ZERO_FRACTION)===json_encode($b,JSON_PRESERVE_ZERO_FRACTION)||(string)$a===(string)$b;}
    private function admin(Request $request):void{abort_unless($request->user()?->access_role==='admin',403);}
}
