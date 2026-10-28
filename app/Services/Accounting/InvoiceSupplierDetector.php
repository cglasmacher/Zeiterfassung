<?php

namespace App\Services\Accounting;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/** Supplier evidence comes from the original PDF, never the forwarding sender. */
class InvoiceSupplierDetector
{
    public function detect(string $pdf): array
    {
        $unknown = ['supplier' => null, 'profile' => null, 'evidence' => null];
        try {
            $text = Process::timeout(20)->run([
                config('accounting.pdftotext_binary', '/usr/bin/pdftotext'),
                '-f', '1', '-l', '1', '-enc', 'UTF-8', $pdf, '-',
            ]);
            if ($text->successful()
                && preg_match('/Handelshof\s+Köln\s+Stiftung\s*&\s*Co\.\s*KG/iu', $text->output())
                && preg_match('/DE\s*123\s*046\s*183/iu', $text->output())) {
                return ['supplier' => 'handelshof', 'profile' => 'handelshof_facturx_v1', 'evidence' => 'pdf_supplier_identifiers'];
            }
            if ($text->successful()
                && preg_match('/getr(?:ä|ae)nke\s+gato\s*(?:gmbh)?/iu', $text->output())
                && preg_match('/(?:getraenke-gato\.de|DE\s*247\s*349\s*452)/iu', $text->output())) {
                return ['supplier' => 'gato', 'profile' => 'gato_layout_v1', 'evidence' => 'pdf_supplier_identifiers'];
            }

            $binary = config('accounting.pdfimages_binary', '/usr/bin/pdfimages');
            $list = Process::timeout(20)->run([$binary, '-f', '1', '-l', '1', '-list', $pdf]);
            if (! $list->successful()) {
                return $unknown;
            }
            // Restrict image expansion to the known native letterhead layout.
            $rows = array_values(array_filter(explode("\n", $list->output()), fn ($line) => preg_match('/^\s*1\s+\d+\s+image\s+/', $line)));
            if (count($rows) !== 1 || ! preg_match('/^\s*1\s+\d+\s+image\s+2457\s+3475\s+icc\s+3\s+8\s+jpeg\s+/', $rows[0])) {
                return $unknown;
            }

            $directory = sys_get_temp_dir().'/accounting-inspect-'.Str::uuid();
            if (! mkdir($directory, 0700)) {
                return $unknown;
            }
            try {
                $result = Process::timeout(20)->run([$binary, '-f', '1', '-l', '1', '-j', $pdf, $directory.'/image']);
                if (! $result->successful()) {
                    return $unknown;
                }
                foreach (glob($directory.'/*.jpg') ?: [] as $image) {
                    $hash = hash_file('sha256', $image);
                    if (in_array($hash, config('accounting.gato_letterhead_hashes', []), true)) {
                        return ['supplier' => 'gato', 'profile' => 'gato_layout_v1', 'evidence' => 'pdf_letterhead_sha256:'.$hash];
                    }
                }
            } finally {
                foreach (glob($directory.'/*') ?: [] as $file) {
                    unlink($file);
                }
                rmdir($directory);
            }
        } catch (RuntimeException) {
            // Unknown is safe: do not force a supplier when inspection is unavailable.
        }

        return $unknown;
    }
}
