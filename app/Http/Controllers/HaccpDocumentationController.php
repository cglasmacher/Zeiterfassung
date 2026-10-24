<?php

namespace App\Http\Controllers;

use App\Services\Haccp\HaccpMonthlyDocumentation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function close(Request $request, int $year, int $month, HaccpMonthlyDocumentation $documentation)
    {
        abort_unless($month >= 1 && $month <= 12, 404);
        abort_if(DB::table('haccp_monthly_closings')->where(['year' => $year, 'month' => $month])->exists(), 409, 'Dieser HACCP-Monat ist bereits abgeschlossen.');

        $snapshot = $documentation->build($year, $month);
        if ($snapshot['statistics']['open_deviations'] > 0) {
            return response()->json(['message' => 'Offene Abweichungen müssen vor dem Monatsabschluss erledigt werden.', 'document' => $snapshot], 422);
        }

        $closingId = DB::table('haccp_monthly_closings')->insertGetId([
            'year' => $year,
            'month' => $month,
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
            'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'statistics' => json_encode($snapshot['statistics'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $closingId, 'document' => $snapshot], 201);
    }
}
