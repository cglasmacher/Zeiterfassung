<?php

namespace App\Services\Accounting;

use RuntimeException;

/** Native one-page ISB invoice layout. Amounts and VAT always come from the original. */
class IsbInvoiceParser
{
    private function fail(string $reason): never
    {
        throw new RuntimeException('ISB-Analyse: '.$reason);
    }

    private function cents(string $value): int
    {
        if ($value === '0') {
            return 0;
        }
        if (! preg_match('/^-?\d+(?:\.\d{3})*,\d{2}$/D', $value)) {
            $this->fail('Ungültiger Geldbetrag.');
        }

        return (int) str_replace(['.', ','], '', $value);
    }

    public function parse(string $text): array
    {
        $pages = array_values(array_filter(explode("\f", $text), fn ($page) => trim($page) !== ''));
        if (count($pages) !== 1 || ! preg_match('/ISB\s+Seafood\s+GmbH/u', $text)
            || ! preg_match('/DE\s*236\s*175\s*659/', $text) || ! preg_match('/^Rechnung\s*$/mu', $text)) {
            $this->fail('Lieferant oder einseitiges Rechnungsformat nicht eindeutig belegt.');
        }
        preg_match_all('/Rechnungsnummer\s+Kundennummer\s+Datum\s*\n\s*(\d+)\s+\d+\s+(\d{2}\.\d{2}\.\d{4})/u', $text, $headers, PREG_SET_ORDER);
        if (count($headers) !== 1) {
            $this->fail('Rechnungsnummer oder Datum fehlt.');
        }
        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $headers[0][2]);
        if (! $date || $date->format('d.m.Y') !== $headers[0][2]) {
            $this->fail('Ungültiges Rechnungsdatum.');
        }
        $money = '-?\d+(?:\.\d{3})*,\d{2}';
        $lines = [];
        $mode = false;
        $finished = false;
        foreach (explode("\n", $text) as $row) {
            if (preg_match('/^Pos\.\s+Menge\s+Artikelnr\.\s+Artikelbezeichnung\s+MwSt\.\s+Einzelpreis\s+Gesamtpreis\s*$/u', $row)) {
                if ($mode || $finished) {
                    $this->fail('Mehrfache Positionstabelle.');
                }
                $mode = true;

                continue;
            }
            if ($mode && str_contains($row, 'Es gelten unsere allgemeinen')) {
                $mode = false;
                $finished = true;
            }
            if (! $mode || trim($row) === '') {
                continue;
            }
            if (preg_match('/^\s*(\d+)\s+(-?\d+,\d{3})\s+(Kg|kg|Stk|St|KT|PA|Krt)\s+(\d{6})\s+(.+?)\s+(7|19)%\s+('.$money.')\s+('.$money.')\s*$/u', $row, $m)) {
                if ((int) $m[1] !== count($lines) + 1) {
                    $this->fail('Positionsfolge ist unvollständig.');
                }
                $net = $this->cents($m[8]);
                $quantity = (float) str_replace(',', '.', $m[2]);
                $price = $this->cents($m[7]);
                if ($price < 0 || abs((int) round($quantity * $price) - $net) > 1 || ($quantity == 0 && $net !== 0)) {
                    $this->fail('Menge × Preis stimmt nicht für Position '.$m[1].'.');
                }
                $lines[] = ['position_no' => (int) $m[1], 'article_number' => $m[4], 'description' => trim($m[5]),
                    'quantity' => $quantity, 'unit' => $m[3], 'net_cents' => $net, 'tax_rate' => (int) $m[6], 'confidence' => 100];
            } else {
                // Wrapped descriptions begin at the article-description column. Reject malformed rows/taxes.
                if (! $lines || ! preg_match('/^ {30,}\S/u', $row) || preg_match('/\d\s*%|\d+,\d{2}/u', $row)) {
                    $this->fail('Position oder Fortsetzungszeile nicht eindeutig lesbar.');
                }
                $lines[array_key_last($lines)]['description'] .= ' '.trim($row);
            }
        }
        if (! $finished || ! $lines || count($lines) > 100) {
            $this->fail('Positionstabelle fehlt oder ist unvollständig.');
        }
        preg_match_all('/Zwischensumme\s+('.$money.')\s*€/u', $text, $nets);
        preg_match_all('/Endbetrag\s+('.$money.')\s*€/u', $text, $grosses);
        preg_match_all('/zzgl\.\s+MwSt\.?\s+\((7|19)%\)\s+('.$money.'|0)\s*€/u', $text, $taxes, PREG_SET_ORDER);
        if (count($nets[1]) !== 1 || count($grosses[1]) !== 1 || count($taxes) !== 2
            || count(array_unique(array_column($taxes, 1))) !== 2) {
            $this->fail('Rechnungsabschluss oder Steuergruppen fehlen.');
        }
        $net = $this->cents($nets[1][0]);
        $gross = $this->cents($grosses[1][0]);
        $groups = [];
        foreach ($taxes as $m) {
            $rate = (int) $m[1];
            $indices = array_keys(array_filter($lines, fn ($line) => $line['tax_rate'] === $rate));
            $groupNet = array_sum(array_map(fn ($i) => $lines[$i]['net_cents'], $indices));
            $tax = $this->cents($m[2]);
            if ((int) round($groupNet * $rate / 100) !== $tax) {
                $this->fail('Positionssumme oder gedruckte Steuergruppe stimmt nicht.');
            }
            $groups[$rate] = ['net_cents' => $groupNet, 'tax_cents' => $tax];
            foreach ($indices as $i) {
                $lines[$i]['tax_cents'] = (int) floor($lines[$i]['net_cents'] * $rate / 100);
            }
            $delta = $tax - array_sum(array_map(fn ($i) => $lines[$i]['tax_cents'], $indices));
            if ($delta < 0 || $delta > count($indices)) {
                $this->fail('Steuerrundung nicht plausibel.');
            }
            usort($indices, function ($a, $b) use ($lines, $rate) {
                $rem = fn ($i) => $lines[$i]['net_cents'] * $rate - $lines[$i]['tax_cents'] * 100;

                return ($rem($b) <=> $rem($a)) ?: ($a <=> $b);
            });
            foreach (array_slice($indices, 0, $delta) as $i) {
                $lines[$i]['tax_cents']++;
            }
        }
        $tax = array_sum(array_column($groups, 'tax_cents'));
        if (array_sum(array_column($lines, 'net_cents')) !== $net || $net + $tax !== $gross) {
            $this->fail('Rechnungssummen stimmen nicht.');
        }
        foreach ($lines as &$line) {
            $line['gross_cents'] = $line['net_cents'] + $line['tax_cents'];
        }
        unset($line);

        return ['invoice_number' => $headers[0][1], 'invoice_date' => $date->format('Y-m-d'), 'currency' => 'EUR',
            'net_cents' => $net, 'tax_cents' => $tax, 'gross_cents' => $gross, 'lines' => $lines,
            'confidence' => 100, 'validation_errors' => [], 'parser_evidence' => ['parser' => 'isb_layout_v1',
                'tax_groups' => $groups, 'rounding' => 'largest_remainder']];
    }
}
