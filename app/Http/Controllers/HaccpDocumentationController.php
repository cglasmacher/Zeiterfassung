<?php

namespace App\Http\Controllers;

use App\Services\Haccp\HaccpMonthlyDocumentation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HaccpDocumentationController extends Controller
{
    public function preview(int $year,int $month,HaccpMonthlyDocumentation $documentation){abort_unless($month>=1&&$month<=12,404);$closing=$this->closing($year,$month);return response()->json(['closed'=>(bool)$closing,'closing'=>$closing,'document'=>$this->document($year,$month,$documentation,$closing)]);}

    public function pdf(int $year,int $month,HaccpMonthlyDocumentation $documentation)
    {
        abort_unless($month>=1&&$month<=12,404);$closing=$this->closing($year,$month);
        if($closing?->pdf_path && Storage::disk('local')->exists($closing->pdf_path)) return Storage::disk('local')->download($closing->pdf_path,sprintf('HACCP_%04d-%02d.pdf',$year,$month),['Content-Type'=>'application/pdf']);
        $document=$this->document($year,$month,$documentation,$closing);
        return response(Pdf::loadView('haccp.monthly-documentation',['document'=>$document,'closed'=>(bool)$closing,'closing'=>$closing])->setPaper('a4','landscape')->output(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.sprintf('HACCP_%04d-%02d.pdf',$year,$month).'"']);
    }

    public function close(Request $request,int $year,int $month,HaccpMonthlyDocumentation $documentation)
    {
        abort_unless($request->user()?->access_role==='admin',403);abort_unless($month>=1&&$month<=12,404);abort_if($this->closing($year,$month),409,'Dieser HACCP-Monat ist bereits abgeschlossen.');
        $snapshot=$documentation->build($year,$month);$blocking=[];
        if($snapshot['statistics']['open_deviations']>0)$blocking[]='Offene Abweichungen';
        if($snapshot['statistics']['open_ccp_deviations']>0)$blocking[]='Offene CCP-Abweichungen';
        if($snapshot['statistics']['cleaning_missing']>0)$blocking[]='Nicht dokumentierte Reinigungen';
        if($snapshot['statistics']['missing_food_samples']>0)$blocking[]='Offene Speisenzubereitungs-Stichproben';
        if($blocking)return response()->json(['message'=>'Monatsabschluss nicht möglich: '.implode(', ',$blocking).'.','blocking'=>$blocking,'document'=>$snapshot],422);

        $documentNumber=sprintf('HACCP-%04d-%02d',$year,$month);$start=sprintf('%04d-%02d-01',$year,$month);$end=date('Y-m-t',strtotime($start));
        $pdfBytes=Pdf::loadView('haccp.monthly-documentation',['document'=>$snapshot,'closed'=>true,'closing'=>(object)['document_number'=>$documentNumber,'closed_at'=>now()]])->setPaper('a4','landscape')->output();
        $path=sprintf('HACCP/%04d/%02d/HACCP_%04d-%02d.pdf',$year,$month,$year,$month);$hash=hash('sha256',$pdfBytes);$size=strlen($pdfBytes);
        Storage::disk('local')->put($path,$pdfBytes);
        try {
            $closingId=DB::transaction(function()use($request,$year,$month,$snapshot,$start,$end,$documentNumber,$path,$hash,$size){
                $templateIds=DB::table('haccp_tasks')->whereBetween('due_at',[$start.' 00:00:00',$end.' 23:59:59'])->distinct()->pluck('template_id');
                $roleIds=DB::table('haccp_tasks')->whereBetween('due_at',[$start.' 00:00:00',$end.' 23:59:59'])->whereNotNull('haccp_role_id')->distinct()->pluck('haccp_role_id');
                $cleaningRoleIds=DB::table('haccp_cleaning_records as r')->join('haccp_cleaning_plans as p','p.id','=','r.cleaning_plan_id')->whereBetween('r.due_date',[$start,$end])->whereNotNull('p.haccp_role_id')->distinct()->pluck('p.haccp_role_id');$roleIds=$roleIds->merge($cleaningRoleIds)->unique();
                if($templateIds->isNotEmpty())DB::table('haccp_templates')->whereIn('id',$templateIds)->whereNull('locked_at')->update(['locked_at'=>now(),'updated_at'=>now()]);
                if($roleIds->isNotEmpty())DB::table('haccp_roles')->whereIn('id',$roleIds)->whereNull('locked_at')->update(['locked_at'=>now(),'updated_at'=>now()]);
                return DB::table('haccp_monthly_closings')->insertGetId(['year'=>$year,'month'=>$month,'closed_by'=>$request->user()->id,'closed_at'=>now(),'document_number'=>$documentNumber,'pdf_path'=>$path,'pdf_sha256'=>$hash,'pdf_size'=>$size,'archived_at'=>now(),'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'statistics'=>json_encode($snapshot['statistics'],JSON_UNESCAPED_UNICODE),'created_at'=>now(),'updated_at'=>now()]);
            });
        } catch (\Throwable $e) {Storage::disk('local')->delete($path);throw $e;}
        return response()->json(['id'=>$closingId,'document_number'=>$documentNumber,'pdf_sha256'=>$hash,'pdf_size'=>$size,'document'=>$snapshot],201);
    }

    private function closing(int $year,int $month): ?object{return DB::table('haccp_monthly_closings')->where(['year'=>$year,'month'=>$month])->first();}
    private function document(int $year,int $month,HaccpMonthlyDocumentation $documentation,?object $closing): array{return $closing&&$closing->snapshot?json_decode($closing->snapshot,true):$documentation->build($year,$month);}
}
