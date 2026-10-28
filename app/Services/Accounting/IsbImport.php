<?php

namespace App\Services\Accounting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class IsbImport
{
    public function prepare(Request $request): array
    {
        $request->validate([
            'extracted_text' => ['nullable', 'string', 'max:2000000'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);
        $raw = ['extraction_profile' => 'isb_layout_v1'];
        try {
            $process = Process::timeout(20)->run([
                config('accounting.pdftotext_binary', '/usr/bin/pdftotext'),
                '-layout', '-enc', 'UTF-8', $request->file('file')->getRealPath(), '-',
            ]);
            if (! $process->successful() || trim($process->output()) === '') {
                throw new RuntimeException('ISB-Analyse: Layout-Text konnte nicht aus dem Original-PDF gelesen werden.');
            }
            $raw['extracted_text'] = $process->output();
            $parsed = app(IsbInvoiceParser::class)->parse($raw['extracted_text']);
            $raw['isb'] = $parsed['parser_evidence'];
            unset($parsed['parser_evidence']);

            return [...$parsed, 'raw_payload' => $raw];
        } catch (RuntimeException $exception) {
            // Keep the original and a visible failed-analysis placeholder. Never fall back to AI amounts.
            $raw['analysis_failed'] = true;

            return [
                'invoice_number' => null, 'invoice_date' => null, 'currency' => 'EUR',
                'net_cents' => null, 'tax_cents' => null, 'gross_cents' => null, 'confidence' => 0,
                'validation_errors' => [str_starts_with($exception->getMessage(), 'ISB-Analyse:')
                    ? $exception->getMessage() : 'ISB-Analyse: PDF-Auswertung fehlgeschlagen.'],
                'raw_payload' => $raw,
                'lines' => [['description' => 'ISB-Analyse fehlgeschlagen – Original-PDF prüfen.']],
            ];
        }
    }
}
