<?php

namespace App\Http\Controllers;

use App\Services\Haccp\HaccpMonthlyDocumentation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HaccpDocumentationController extends Controller
{
    public function preview(int $year, int $month, HaccpMonthlyDocumentation $documentation)
    {
        abort_unless($month >= 1 && $month <= 12, 404);
        $closing = DB::table('haccp_monthly_closings')->where(['year' => $year, 'month' => $month])->first();

        return response()->json([
            'closed' => (bool) $closing,
            'closing' => $closing,
            'document' => $closing && $closing->snapshot ? json_decode($closing->snapshot, true) : $documentation->build($year, $month),
        ]);
    }

    public function pdf(int $year, int $month, HaccpMonthlyDocumentation $documentation)
    {
        abort_unless($month >= 1 && $month <= 12, 404);
        $closing = DB::table('haccp_monthly_closings')->where(['year' => $year, 'month' => $month])->first();

        if ($closing?->document_path && Storage::disk('local')->exists($closing->document_path)) {
            return Storage::disk('local')->download($closing->document_path, $this->filename($year, $month));
        }

        $document = $closing?->snapshot ? json_decode($closing->snapshot, true) : $documentation->build($year, $month);
        return $this->renderPdf($document)->download($this->filename($year, $month));
    }

    public function close(Request $request, int $year, int $month, HaccpMonthlyDocumentation $documentation)
    {
        abort_unless($month >= 1 && $month <= 12, 404);
        abort_if(DB::table('haccp_monthly_closings')->where(['year' => $year, 'month' => $month])->exists(), 409, 'Dieser HACCP-Monat ist bereits abgeschlossen.');

        $snapshot = $documentation->build($year, $month);
        if ($snapshot['statistics']['open_deviations'] > 0) {
            return response()->json(['message' => 'Offene Abweichungen müssen vor dem Monatsabschluss erledigt werden.', 'document' => $snapshot], 422);
        }

        $pdfBytes = $this->renderPdf($snapshot)->output();
        $path = sprintf('haccp/%04d/%02d/HACCP_%04d-%02d.pdf', $year, $month, $year, $month);
        Storage::disk('local')->put($path, $pdfBytes);
        $hash = hash('sha256', $pdfBytes);

        $closingId = DB::table('haccp_monthly_closings')->insertGetId([
            'year' => $year,
            'month' => $month,
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'statistics' => json_encode($snapshot['statistics'], JSON_UNESCAPED_UNICODE),
            'document_path' => $path,
            'document_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'id' => $closingId,
            'document' => $snapshot,
            'document_hash' => $hash,
            'pdf_url' => sprintf('/api/haccp/documentation/%d/%d/pdf', $year, $month),
        ], 201);
    }

    private function renderPdf(array $document)
    {
        return Pdf::loadView('haccp.monthly-documentation', compact('document'))
            ->setPaper('a4', 'landscape');
    }

    private function filename(int $year, int $month): string
    {
        return sprintf('HACCP_%04d-%02d.pdf', $year, $month);
    }
}
