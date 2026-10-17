<?php

namespace App\Http\Controllers;

use App\Services\Cash\ZReportMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ZReportController extends Controller
{
    public static function listing($query)
    {
        return $query->orderByDesc('created_at')->get([
            'id', 'original_name', 'report_number', 'register_name', 'reported_at',
            'business_date', 'assignment_source', 'created_at',
        ])->map(fn ($row) => [...(array) $row,
            'pdf_url' => route('cash.z-reports.pdf', ['id' => $row->id], absolute: false)]);
    }

    public function store(Request $request, ZReportMetadata $parser)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
            'extracted_text' => 'nullable|string|max:1000000',
            'message_id' => 'nullable|string|max:500',
        ]);
        $file = $request->file('file');
        $bytes = file_get_contents($file->getRealPath());
        if (! str_starts_with($bytes, '%PDF-')) {
            throw ValidationException::withMessages(['file' => 'Keine gültige PDF-Datei.']);
        }
        $hash = hash('sha256', $bytes);

        // Per-content lock serializes retries; a repeated email never changes assignment.
        return \Illuminate\Support\Facades\Cache::lock('cash-z-report-'.$hash, 60)->block(5, function () use ($hash, $bytes, $file, $data, $parser) {
            $existing = DB::table('cash_z_reports')->where('sha256', $hash)->first();
            if ($existing) {
                return response()->json(['id' => $existing->id, 'duplicate' => true, 'business_date' => $existing->business_date]);
            }
            $path = 'cash/z-reports/'.$hash.'.pdf';
            abort_unless(Storage::disk('local')->put($path, $bytes), 500, 'PDF konnte nicht gespeichert werden.');
            $metadata = $parser->parse($data['extracted_text'] ?? '');
            $id = DB::table('cash_z_reports')->insertGetId([
                ...$metadata, 'sha256' => $hash, 'path' => $path,
                'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255),
                'message_id' => $data['message_id'] ?? null, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return response()->json(['id' => $id, 'duplicate' => false,
                'business_date' => $metadata['business_date'],
                'needs_assignment' => $metadata['business_date'] === null], 201);
        });
    }

    public function index(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d']);

        return response()->json([
            'reports' => self::listing(DB::table('cash_z_reports')->where('business_date', $data['date'])),
            'unassigned' => self::listing(DB::table('cash_z_reports')->whereNull('business_date')->limit(100)),
            'recent' => self::listing(DB::table('cash_z_reports')->whereNotNull('business_date')->limit(30)),
        ]);
    }

    public function assign(Request $request, int $id)
    {
        $data = $request->validate(['business_date' => 'required|date_format:Y-m-d']);
        DB::transaction(function () use ($request, $id, $data) {
            $report = DB::table('cash_z_reports')->lockForUpdate()->find($id);
            abort_unless($report, 404);
            $history = json_decode($report->assignment_history ?? '[]', true);
            $history[] = ['from' => $report->business_date, 'to' => $data['business_date'],
                'user_id' => $request->user()->id, 'at' => now()->toIso8601String()];
            DB::table('cash_z_reports')->where('id', $id)->update([
                'business_date' => $data['business_date'], 'assignment_source' => 'manual',
                'assignment_history' => json_encode($history, JSON_THROW_ON_ERROR), 'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Z-Bericht zugeordnet.']);
    }

    public function pdf(int $id)
    {
        $report = DB::table('cash_z_reports')->find($id);
        abort_unless($report && Storage::disk('local')->exists($report->path), 404);

        return Storage::disk('local')->response($report->path, 'Z-Bericht-'.$id.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
