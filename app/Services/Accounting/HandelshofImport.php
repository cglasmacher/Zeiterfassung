<?php

namespace App\Services\Accounting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class HandelshofImport
{
    public function prepare(Request $request): array
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf', 'max:20480']]);
        $raw = ['extraction_profile' => 'handelshof_facturx_v1'];
        $directory = sys_get_temp_dir().'/accounting-handelshof-'.Str::uuid();
        try {
            if (! mkdir($directory, 0700)) {
                throw new RuntimeException('Handelshof-Analyse: Temporäres Verzeichnis konnte nicht angelegt werden.');
            }
            $pdf = $request->file('file')->getRealPath();
            $xmlPath = $directory.'/invoice.xml';
            $extract = Process::timeout(20)->run([
                config('accounting.pdfdetach_binary', '/usr/bin/pdfdetach'),
                '-savefile', 'factur-x.xml', '-o', $xmlPath, $pdf,
            ]);
            if (! $extract->successful() || ! is_file($xmlPath) || filesize($xmlPath) > 2000000) {
                throw new RuntimeException('Handelshof-Analyse: Eingebettete factur-x.xml fehlt oder konnte nicht gelesen werden.');
            }
            $text = Process::timeout(20)->run([
                config('accounting.pdftotext_binary', '/usr/bin/pdftotext'),
                '-layout', '-enc', 'UTF-8', $pdf, '-',
            ]);
            if (! $text->successful() || trim($text->output()) === '') {
                throw new RuntimeException('Handelshof-Analyse: Sichtbarer PDF-Text fehlt für den Abgleich.');
            }
            $xml = file_get_contents($xmlPath);
            $raw['xml_sha256'] = hash('sha256', $xml);
            $raw['extracted_text'] = $text->output();
            $parsed = app(HandelshofInvoiceParser::class)->parse($xml, $text->output());
            $raw['handelshof'] = $parsed['parser_evidence'];
            unset($parsed['parser_evidence']);

            return [...$parsed, 'raw_payload' => $raw];
        } catch (RuntimeException $exception) {
            $raw['analysis_failed'] = true;

            return [
                'invoice_number' => null, 'invoice_date' => null, 'currency' => 'EUR',
                'net_cents' => null, 'tax_cents' => null, 'gross_cents' => null, 'confidence' => 0,
                'validation_errors' => [str_starts_with($exception->getMessage(), 'Handelshof-Analyse:')
                    ? $exception->getMessage() : 'Handelshof-Analyse: PDF-Auswertung fehlgeschlagen.'],
                'raw_payload' => $raw,
                'lines' => [['description' => 'Handelshof-Analyse fehlgeschlagen – Original-PDF prüfen.']],
            ];
        } finally {
            if (is_dir($directory)) {
                if (is_file($directory.'/invoice.xml')) {
                    unlink($directory.'/invoice.xml');
                }
                rmdir($directory);
            }
        }
    }
}
