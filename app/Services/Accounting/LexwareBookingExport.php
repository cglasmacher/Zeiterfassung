<?php

namespace App\Services\Accounting;

use App\Models\AccountingDocument;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LexwareBookingExport
{
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['export' => $message]);
    }

    public function prepare(Collection $documents): array
    {
        $rows = [];
        $snapshots = [];
        $total = 0;
        foreach ($documents as $document) {
            $label = 'Beleg '.$document->id.' ('.$document->invoice_number.')';
            if ($document->status !== 'approved' || ! $document->approved_at || ! $document->approved_by
                || $document->export_batch_id || $document->exported_at) {
                $this->reject($label.': nicht freigegeben oder bereits exportiert.');
            }
            $document->loadMissing(['supplier', 'lines.account']);
            $issues = app(DocumentValidation::class)->errors($document);
            if ($issues) {
                $this->reject($label.': '.implode(' · ', $issues));
            }
            if ($document->currency !== 'EUR') {
                $this->reject($label.': nur EUR wird unterstützt.');
            }
            if (mb_strlen($document->invoice_number) > 20 || preg_match('/[\x00-\x1F\x7F]/', $document->invoice_number)) {
                $this->reject($label.': Belegnummer überschreitet die Lexware-Grenze von 20 Zeichen.');
            }
            $creditor = $document->supplier->creditor_account;
            if (! preg_match('/^[1-9]\d{0,8}$/', $creditor)) {
                $this->reject($label.': ungültiges Kreditorenkonto.');
            }
            $summary = app(BookingSummary::class)->forDocument($document);
            if ($summary['unresolved_lines'] || $summary['deposit_postings']['errors']) {
                $this->reject($label.': Buchungsvorbereitung unvollständig.');
            }
            if (array_sum(array_column($summary['rows'], 'gross_cents')) !== $document->gross_cents) {
                $this->reject($label.': Kontosummen entsprechen nicht exakt dem Rechnungsbrutto.');
            }
            $start = count($rows);
            foreach ($summary['rows'] as $group) {
                $account = $group['account_number'];
                if (! preg_match('/^[1-9]\d{0,8}$/', $account) || $account === $creditor) {
                    $this->reject($label.': ungültiges Sachkonto.');
                }
                if ($group['gross_cents'] !== $group['net_cents'] + $group['tax_cents']) {
                    $this->reject($label.': Netto und Steuer sind nicht exakt abgestimmt.');
                }
                if ($account === '1141') {
                    // Two double-entry records, not a second gross posting on 1141.
                    $this->append($rows, $document, '1141', $creditor, $group['net_cents'], '', 'Pfand netto');
                    $this->append($rows, $document, '1461', $creditor, $group['tax_cents'], '', 'Vorsteuer Pfand');

                    continue;
                }
                $rate = (float) $group['tax_rate'];
                $key = match ($rate) {
                    0.0 => '', 7.0 => '8', 19.0 => '9', default => null
                };
                if ($key === null) {
                    $this->reject($label.': Steuersatz nicht unterstützt.');
                }
                $calculated = (int) round($group['gross_cents'] * $rate / (100 + $rate));
                if ($calculated !== $group['tax_cents']) {
                    $this->reject($label.': Steuer bei Konto '.$account.' weicht von der automatischen Lexware-Berechnung ab; keine geratene Rundungsbuchung.');
                }
                $this->append($rows, $document, $account, $creditor, $group['gross_cents'], $key, $group['account_name']);
            }
            $documentRows = array_slice($rows, $start);
            if (! $documentRows) {
                $this->reject($label.': keine Buchungssätze mit einem Betrag ungleich null.');
            }
            $creditorMovement = array_sum(array_map(fn ($row) => $row['credit_account'] === $creditor ? $row['amount_cents'] : -$row['amount_cents'], $documentRows));
            if ($creditorMovement !== $document->gross_cents) {
                $this->reject($label.': Kreditorensumme ist nicht ausgeglichen.');
            }
            $snapshots[] = ['id' => $document->id, 'invoice_number' => $document->invoice_number,
                'supplier' => $document->supplier->name, 'creditor' => $creditor,
                'invoice_date' => $document->invoice_date->format('Y-m-d'), 'gross_cents' => $document->gross_cents,
                'approved_at' => $document->approved_at->toISOString(), 'row_count' => count($documentRows)];
            $total += $document->gross_cents;
        }
        if (! $rows) {
            $this->reject('Keine exportierbaren Buchungssätze vorhanden.');
        }
        if (count($rows) > 5000) {
            $this->reject('Maximal 5.000 Buchungssätze pro Export.');
        }
        $csv = $this->csv($rows);

        return ['documents' => $snapshots, 'rows' => $rows, 'row_count' => count($rows),
            'total_cents' => $total, 'fingerprint' => hash('sha256', $csv.json_encode($snapshots)), 'csv' => $csv];
    }

    private function append(array &$rows, AccountingDocument $document, string $account, string $creditor, int $amount, string $taxKey, string $description): void
    {
        if ($amount === 0) {
            return;
        }
        if (abs($amount) > 999999999999) {
            $this->reject('Buchungsbetrag überschreitet die Lexware-Feldlänge.');
        }
        $rows[] = ['document_id' => $document->id, 'date' => $document->invoice_date->format('d.m.Y'),
            'invoice_number' => $document->invoice_number,
            'text' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $document->supplier->name.' · '.$description), 0, 79),
            'amount_cents' => abs($amount), 'debit_account' => $amount > 0 ? $account : $creditor,
            'credit_account' => $amount > 0 ? $creditor : $account, 'tax_key' => $taxKey];
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['Belegdatum', 'Belegnummernkreis', 'Belegnummer', 'Buchungstext', 'Buchungsbetrag', 'Sollkonto', 'Habenkonto', 'Steuerschlüssel'], ';', '"', '', "\r\n");
        foreach ($rows as $row) {
            $amount = intdiv($row['amount_cents'], 100).','.str_pad((string) ($row['amount_cents'] % 100), 2, '0', STR_PAD_LEFT);
            fputcsv($stream, [$row['date'], '', $row['invoice_number'], $row['text'], $amount, $row['debit_account'], $row['credit_account'], $row['tax_key']], ';', '"', '', "\r\n");
        }
        rewind($stream);
        $utf8 = stream_get_contents($stream);
        fclose($stream);
        $encoded = @iconv('UTF-8', 'Windows-1252', $utf8);
        if ($encoded === false) {
            $this->reject('Ein Text enthält Zeichen außerhalb von Windows-1252. Bitte vor dem Export korrigieren.');
        }

        return $encoded;
    }
}
