<?php

namespace App\Services\Cash;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ZReportTextExtractor
{
    public function extract(string $path): string
    {
        $binary = (string) config('cash.z_reports.pdftotext_binary', '/usr/bin/pdftotext');
        if ($binary === '') {
            return '';
        }
        try {
            // Fixed arguments, no shell interpolation. Only the first 20 pages are needed.
            $result = Process::timeout(20)->run([$binary, '-f', '1', '-l', '20', '-layout', '-enc', 'UTF-8', $path, '-']);
            if ($result->successful()) {
                return mb_substr($result->output(), 0, 1000000);
            }
        } catch (\Throwable) {
            // Preserve the original PDF even if the binary is unavailable or times out.
        }
        Log::warning('Z-Bericht: Server-Textextraktion fehlgeschlagen. poppler-utils, CASH_Z_REPORT_PDFTOTEXT_BINARY und PHP proc_open prüfen.');

        return '';
    }
}
