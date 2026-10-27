<?php

namespace App\Services\Accounting;

use App\Models\AccountingDocument;
use Illuminate\Support\Facades\Storage;

class DocumentValidation
{
    /** Blocking issues shared by readiness and approval. No tax is inferred here. */
    public function errors(AccountingDocument $document): array
    {
        $document->loadMissing(['supplier', 'lines.account']);
        $errors = $document->validation_errors ?? [];

        if (! $document->supplier?->active || ! $document->supplier?->creditor_account) {
            $errors[] = 'Aktiver Lieferant mit Kreditorenkonto fehlt.';
        }
        if (! trim((string) $document->invoice_number) || ! $document->invoice_date) {
            $errors[] = 'Rechnungsnummer oder Rechnungsdatum fehlt.';
        }
        if (! $document->file_path || ! Storage::disk('local')->exists($document->file_path)) {
            $errors[] = 'Original-PDF fehlt.';
        }
        if ($document->lines->isEmpty()) {
            $errors[] = 'Der Beleg enthält keine Buchungszeilen.';
        }

        foreach ($document->lines as $line) {
            $prefix = 'Position '.($line->position_no ?? $line->id).': ';
            if (! $line->account?->active) {
                $errors[] = $prefix.'aktives Sachkonto fehlt.';
            }
            if ($line->tax_rate === null || ! in_array((float) $line->tax_rate, [0.0, 7.0, 19.0], true)) {
                $errors[] = $prefix.'belegter Steuersatz fehlt.';
            }
            if (in_array($line->status, ['review', 'manual'], true)) {
                $errors[] = $prefix.'manuelle Prüfung erforderlich.';
            }
            if ($line->net_cents === null || $line->tax_cents === null || $line->gross_cents === null) {
                $errors[] = $prefix.'Netto-, Steuer- oder Bruttobetrag fehlt.';
            } elseif (abs($line->net_cents + $line->tax_cents - $line->gross_cents) > 2) {
                $errors[] = $prefix.'Netto + USt entspricht nicht Brutto.';
            }
        }

        foreach (['net_cents' => 'Netto', 'tax_cents' => 'USt', 'gross_cents' => 'Brutto'] as $field => $label) {
            if ($document->{$field} === null) {
                $errors[] = 'Rechnungsbetrag '.$label.' fehlt.';
            } elseif (abs($document->lines->sum($field) - $document->{$field}) > 2) {
                $errors[] = 'Positionssumme '.$label.' weicht vom Rechnungsbetrag ab.';
            }
        }
        if ($document->net_cents !== null && $document->tax_cents !== null && $document->gross_cents !== null
            && abs($document->net_cents + $document->tax_cents - $document->gross_cents) > 2) {
            $errors[] = 'Rechnungsnetto + USt entspricht nicht Brutto.';
        }

        foreach (($document->raw_payload['gato']['tax_groups'] ?? []) as $rate => $group) {
            $lines = $document->lines->filter(fn ($line) => (float) $line->tax_rate === (float) $rate);
            if ($lines->sum('net_cents') !== $group['net_cents'] || $lines->sum('tax_cents') !== $group['tax_cents']) {
                $errors[] = 'Gato-Steuergruppe '.$rate.' % stimmt nicht mit dem Original überein.';
            }
        }

        return array_values(array_unique($errors, SORT_REGULAR));
    }
}
