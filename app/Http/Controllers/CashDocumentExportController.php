<?php

namespace App\Http\Controllers;

use App\Services\Cash\CashDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashDocumentExportController extends Controller
{
    public function index(CashDocument $documents)
    {
        $result = DB::table('cash_handovers')->whereNotExists(function ($query) {
            $query->selectRaw('1')->from('cash_document_archives')->where('kind', 'handover')
                ->whereColumn('source_id', 'cash_handovers.id');
        })->orderBy('id')->limit(10)->get(['id'])->map(fn ($row) => $documents->describe('handover', $row->id));
        $days = DB::table('cash_days')->where('status', 'finalized')->whereNotExists(function ($query) {
            $query->selectRaw('1')->from('cash_document_archives')->where('kind', 'day')
                ->whereColumn('source_id', 'cash_days.id');
        })->orderBy('business_date')->limit(10)->get(['id'])->map(fn ($row) => $documents->describe('day', $row->id));

        return response()->json(['documents' => $result->concat($days)->values()]);
    }

    public function pdf(CashDocument $documents, string $kind, int $id)
    {
        $document = $documents->describe($kind, $id);

        return response($documents->render($kind, $id))->withHeaders([
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$document['filename'].'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function acknowledge(Request $request, CashDocument $documents, string $kind, int $id)
    {
        $document = $documents->describe($kind, $id);
        $data = $request->validate(['remote_path' => 'required|string|max:500']);
        DB::table('cash_document_archives')->insertOrIgnore([
            'document_key' => $document['document_key'], 'kind' => $kind, 'source_id' => $id, 'remote_path' => $data['remote_path'], 'archived_at' => now(),
        ]);

        return response()->json(['accepted' => true, 'document_key' => $document['document_key']]);
    }
}
