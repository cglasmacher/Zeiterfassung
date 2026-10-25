<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HaccpEvidenceController extends Controller
{
    private const SOURCES = [
        'task_result' => 'haccp_task_results',
        'goods_receipt' => 'haccp_goods_receipts',
        'food_sample' => 'haccp_food_samples',
        'retention_sample' => 'haccp_retention_samples',
        'cleaning_record' => 'haccp_cleaning_records',
        'maintenance_record' => 'haccp_maintenance_records',
        'deviation' => 'haccp_deviations',
    ];

    public function index(Request $request, string $type, int $id)
    {
        $this->source($type, $id);
        return response()->json(DB::table('haccp_evidence_files as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.uploaded_by')
            ->where(['e.source_type' => $type, 'e.source_id' => $id])
            ->select('e.id', 'e.source_type', 'e.source_id', 'e.original_name', 'e.mime_type', 'e.size', 'e.sha256', 'e.captured_at', 'e.created_at', 'u.name as uploaded_by_name')
            ->orderBy('e.id')->get());
    }

    public function store(Request $request, string $type, int $id)
    {
        $this->source($type, $id);
        $data = $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:12288'],
            'captured_at' => ['nullable', 'date'],
        ]);
        $file = $data['photo'];
        $bytes = file_get_contents($file->getRealPath());
        $sha = hash('sha256', $bytes);
        $existing = DB::table('haccp_evidence_files')->where(['source_type' => $type, 'source_id' => $id, 'sha256' => $sha])->first();
        if ($existing) return response()->json(['id' => $existing->id, 'duplicate' => true]);
        $path = $file->store("haccp/evidence/{$type}/{$id}", 'local');
        $now = now();
        $evidenceId = DB::table('haccp_evidence_files')->insertGetId([
            'source_type' => $type, 'source_id' => $id, 'uploaded_by' => $request->user()->id,
            'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(),
            'sha256' => $sha, 'captured_at' => $data['captured_at'] ?? null, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return response()->json(['id' => $evidenceId, 'duplicate' => false], 201);
    }

    public function show(Request $request, int $id)
    {
        $row = DB::table('haccp_evidence_files')->find($id);
        abort_unless($row, 404);
        abort_unless(Storage::disk($row->disk)->exists($row->path), 404, 'Nachweisdatei nicht gefunden.');
        return Storage::disk($row->disk)->response($row->path, $row->original_name, ['Content-Type' => $row->mime_type, 'Cache-Control' => 'private, max-age=3600']);
    }

    private function source(string $type, int $id): void
    {
        abort_unless(isset(self::SOURCES[$type]), 404);
        abort_unless(DB::table(self::SOURCES[$type])->where('id', $id)->exists(), 404, 'HACCP-Nachweis nicht gefunden.');
    }
}
