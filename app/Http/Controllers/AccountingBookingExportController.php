<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\AccountingExportBatch;
use App\Services\Accounting\LexwareBookingExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingBookingExportController extends Controller
{
    private function selection(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'document_ids' => [$creating ? 'required' : 'sometimes', 'array', 'min:1', 'max:500'],
            'document_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'fingerprint' => [$creating ? 'required' : 'sometimes', 'string', 'size:64'],
        ]);
    }

    private function documents(array $data, bool $lock = false)
    {
        $query = AccountingDocument::where('status', 'approved')->whereNull('export_batch_id')->whereNull('exported_at')
            ->whereYear('invoice_date', $data['year'])->orderBy('id');
        if (isset($data['document_ids'])) {
            $query->whereIn('id', $data['document_ids']);
        }
        if ($lock) {
            $query->lockForUpdate();
        }
        $documents = $query->limit(501)->get();
        if ($documents->isEmpty() || $documents->count() > 500
            || (isset($data['document_ids']) && $documents->count() !== count($data['document_ids']))) {
            throw ValidationException::withMessages(['export' => 'Auswahl enthält keine, zu viele oder nicht mehr exportierbare Belege. Vorschau neu laden.']);
        }

        return $documents;
    }

    public function preview(Request $request, LexwareBookingExport $export)
    {
        $data = $this->selection($request);
        $preview = $export->prepare($this->documents($data));
        unset($preview['csv']);

        return response()->json($preview);
    }

    public function store(Request $request, LexwareBookingExport $export)
    {
        $data = $this->selection($request, true);
        $batch = DB::transaction(function () use ($request, $data, $export) {
            $documents = $this->documents($data, true);
            $prepared = $export->prepare($documents);
            abort_unless(hash_equals($data['fingerprint'], $prepared['fingerprint']), 409, 'Buchungsdaten haben sich geändert. Vorschau neu laden.');
            $batch = AccountingExportBatch::create([
                'status' => 'generated', 'generated_by' => $request->user()->id, 'generated_at' => now(),
                'row_count' => $prepared['row_count'], 'total_cents' => $prepared['total_cents'],
                'metadata' => ['format' => 'lexware_ascii_ansi_v1', 'year' => $data['year'],
                    'documents' => $prepared['documents'], 'rows' => $prepared['rows'],
                    'csv_base64' => base64_encode($prepared['csv']), 'sha256' => hash('sha256', $prepared['csv'])],
            ]);
            $batch->update(['filename' => 'Lexware-Eingangsrechnungen-'.$data['year'].'-'.$batch->id.'.csv']);
            foreach ($documents as $document) {
                $document->update(['status' => 'exported', 'export_batch_id' => $batch->id, 'exported_at' => now()]);
            }

            return $batch;
        });

        return response()->json(['batch' => ['id' => $batch->id, 'filename' => $batch->filename],
            'download_url' => '/api/accounting/exports/'.$batch->id.'/download'], 201);
    }

    public function index()
    {
        return response()->json(['batches' => AccountingExportBatch::where('status', 'generated')->latest('id')->limit(20)
            ->get(['id', 'filename', 'row_count', 'total_cents', 'generated_at'])]);
    }

    public function download(int $id)
    {
        $batch = AccountingExportBatch::where('status', 'generated')->findOrFail($id);
        $csv = base64_decode($batch->metadata['csv_base64'] ?? '', true);
        abort_unless(is_string($csv) && $csv !== '' && hash_equals($batch->metadata['sha256'] ?? '', hash('sha256', $csv)), 409, 'Exportdatei fehlt oder ist beschädigt.');

        return response($csv)->header('Content-Type', 'text/csv; charset=Windows-1252')
            ->header('Content-Disposition', 'attachment; filename="'.$batch->filename.'"')
            ->header('Cache-Control', 'private, no-store');
    }
}
