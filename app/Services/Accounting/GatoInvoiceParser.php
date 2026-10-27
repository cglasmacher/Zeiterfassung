<?php

namespace App\Services\Accounting;

use RuntimeException;

/** Strict parser for Gato's native, layout-preserved invoice text. Fails closed. */
class GatoInvoiceParser
{
    private function fail(string $reason): never
    {
        throw new RuntimeException('Gato-Analyse: '.$reason);
    }

    private function cents(string $value): int
    {
        $negative = str_starts_with($value, '-') || str_ends_with($value, '-');
        $value = trim($value, '-');
        if (! preg_match('/^\d+(?:\.\d{3})*,\d{2}$/', $value)) {
            $this->fail('Ungültiger Geldbetrag.');
        }

        return (int) str_replace(['.', ','], '', $value) * ($negative ? -1 : 1);
    }

    public function parse(string $text): array
    {
        $text = str_replace(["\r", "\xc2\xa0", "\f"], ['', ' ', "\n"], $text);
        preg_match_all('/Seite\s+(\d+)\/(\d+)/u', $text, $pages, PREG_SET_ORDER);
        if (! $pages || count(array_unique(array_column($pages, 2))) !== 1
            || array_values(array_unique(array_map('intval', array_column($pages, 1)))) !== range(1, (int) $pages[0][2])) {
            $this->fail('Seiten fehlen oder Seitenfolge ist unklar.');
        }
        preg_match_all('/Beleg-Nr\.:\s*(\S+)/u', $text, $numbers);
        preg_match_all('/(?<![\pL])Datum:\s*(\d{2}\.\d{2}\.\d{4})/u', $text, $dates);
        if (count(array_unique($numbers[1])) !== 1 || count(array_unique($dates[1])) !== 1) {
            $this->fail('Rechnungsnummer oder Datum fehlt bzw. widerspricht sich.');
        }
        $date = \DateTimeImmutable::createFromFormat('!d.m.Y', $dates[1][0]);
        if (! $date || $date->format('d.m.Y') !== $dates[1][0]) {
            $this->fail('Rechnungsdatum ist ungültig.');
        }

        $money = '-?\d+(?:\.\d{3})*,\d{2}-?';
        $goods = [];
        $deposits = [];
        $groups = [];
        $mode = null;
        $depositAmountColumn = null;
        $goodsTotal = null;
        $depositTotal = null;
        $gross = null;
        foreach (explode("\n", $text) as $row) {
            if (preg_match('/^\s*Art\.\s+Artikelbezeichnung/u', $row)) {
                $mode = 'goods';

                continue;
            }
            if (str_contains($row, 'Warenwert') && str_contains($row, 'Nettowert') && str_contains($row, 'Gesamt')) {
                $mode = 'summary';

                continue;
            }
            if (str_contains($row, 'Leergutart') && str_contains($row, 'Leergut-Rückgabe')) {
                $mode = 'deposit';
                $depositAmountColumn = mb_strpos($row, 'Betrag');

                continue;
            }
            if (str_contains($row, 'Zahlungsziel') || preg_match('/Seite\s+\d+\/\d+/', $row)) {
                $mode = null;
            }
            if ($mode === 'goods' && preg_match('/^(\d{5})\s+(.+?)\s+(-?\d+(?:,\d+)?-?)\s+(Fl|Ka|Stk|Fa|Fass|Keg|KEG|Kt|Ds)\s+(?:('.$money.')\s+)?(-?\d+(?:\.\d{3})*,\d{4}-?)\s+('.$money.')(?:\s+(\*))?\s*$/u', $row, $m)) {
                $description = preg_replace('/\s+(?:\d+\s*x\s*)?\d+,\d+\s*$/u', '', $m[2]);
                $net = $this->cents($m[7]);
                $quantity = (float) str_replace(',', '.', trim($m[3], '-')) * (str_contains($m[3], '-') ? -1 : 1);
                $price = (float) str_replace(['.', ','], ['', '.'], trim($m[6], '-')) * (str_contains($m[6], '-') ? -1 : 1);
                if (abs((int) round($quantity * $price * 100) - $net) > 1) {
                    $this->fail('Menge × Preis stimmt nicht für Artikel '.$m[1].'.');
                }
                $goods[] = [
                    'article_number' => $m[1], 'description' => trim($description),
                    'quantity' => $quantity, 'unit' => $m[4], 'net_cents' => $net,
                    'tax_rate' => ($m[8] ?? '') === '*' ? 7 : 19,
                    'evidence' => ['type' => 'goods', 'star' => ($m[8] ?? '') === '*', 'raw' => trim($row)],
                ];

                continue;
            }
            if ($mode === 'goods' && preg_match('/^\s*\d{5}\b/', $row)) {
                $this->fail('Warenposition ist nicht vollständig lesbar.');
            }
            if ($mode === 'goods' && trim($row) !== '' && $goods) {
                // Only indented text-only continuation lines are accepted.
                if (! preg_match('/^\s+\pL[^\d]*$/u', $row)) {
                    $this->fail('Unbekannte Fortsetzungszeile.');
                }
                $goods[array_key_last($goods)]['description'] .= ' '.trim($row);
            }
            if ($mode === 'summary' && trim($row) !== '') {
                $tokens = preg_split('/\s+/', trim($row));
                if ($goodsTotal === null) {
                    if (count($tokens) < 6) {
                        $this->fail('Summenblock ist unlesbar.');
                    }
                    $goodsTotal = $this->cents(array_shift($tokens));
                    $depositTotal = $this->cents(array_shift($tokens));
                    $gross = $this->cents(array_pop($tokens));
                }
                $star = in_array('*', $tokens, true);
                $tokens = array_values(array_filter($tokens, fn ($v) => $v !== '*'));
                if (count($tokens) !== 3 || ! in_array($tokens[1], ['7,00', '19,00'], true)) {
                    $this->fail('Steuergruppe ist unlesbar.');
                }
                $rate = $tokens[1] === '7,00' ? 7 : 19;
                if ($star !== ($rate === 7) || isset($groups[$rate])) {
                    $this->fail('Steuerkennzeichnung ist widersprüchlich.');
                }
                $groups[$rate] = ['net_cents' => $this->cents($tokens[0]), 'tax_cents' => $this->cents($tokens[2])];
            }
            if ($mode === 'deposit' && preg_match('/^(\d{5})\s+(.+?)\s{2,}/u', $row, $m)) {
                $amountText = trim(mb_substr($row, max(0, $depositAmountColumn - 2)));
                $amount = $amountText === '' ? 0 : $this->cents($amountText);
                $deposits[] = ['article_number' => $m[1], 'description' => 'Pfand / Leergut '.trim($m[2]),
                    'quantity' => null, 'unit' => null, 'net_cents' => $amount,
                    'evidence' => ['type' => 'deposit', 'raw' => trim($row)]];
            } elseif ($mode === 'deposit' && trim($row) !== '') {
                $this->fail('Leergutposition ist nicht vollständig lesbar.');
            }
        }
        if (! $goods || $goodsTotal === null || ! $groups || array_sum(array_column($goods, 'net_cents')) !== $goodsTotal
            || array_sum(array_column($deposits, 'net_cents')) !== $depositTotal) {
            $this->fail('Waren- oder Pfandsummen stimmen nicht mit dem Beleg überein.');
        }

        // Identify the deposit's tax group solely through exact invoice reconciliation.
        $remaining = [];
        foreach ($groups as $rate => $group) {
            $sum = array_sum(array_column(array_filter($goods, fn ($l) => $l['tax_rate'] === $rate), 'net_cents'));
            $remaining[$rate] = $group['net_cents'] - $sum;
        }
        if ($depositTotal !== 0) {
            $matches = array_keys(array_filter($remaining, fn ($v) => $v === $depositTotal));
            if (count($matches) !== 1) {
                $this->fail('Steuergruppe für Pfand ist nicht eindeutig.');
            }
            $depositRate = $matches[0];
            foreach ($remaining as $rate => $value) {
                if ($rate !== $depositRate && $value !== 0) {
                    $this->fail('Steuergruppen stimmen nicht.');
                }
            }
        } else {
            if (array_filter($remaining)) {
                $this->fail('Steuergruppen stimmen nicht.');
            }
            // Zero-saldo deposits can still contain taxable purchases and returns.
            if (array_filter($deposits, fn ($l) => $l['net_cents'] !== 0)) {
                $this->fail('Steuergruppe bei saldiertem Pfand ist nicht eindeutig.');
            }
            $depositRate = null;
        }
        $lines = $goods;
        foreach ($deposits as $line) {
            if ($line['net_cents'] !== 0) {
                $lines[] = [...$line, 'tax_rate' => $depositRate];
            }
        }
        foreach ($lines as $line) {
            if (! isset($groups[$line['tax_rate']])) {
                $this->fail('Steuergruppe für Warenposition fehlt.');
            }
        }

        // Largest remainder, including signed deposit rows, preserves printed group tax.
        foreach ($groups as $rate => $group) {
            if (abs((int) round($group['net_cents'] * $rate / 100) - $group['tax_cents']) > 1) {
                $this->fail('Gruppensteuer stimmt rechnerisch nicht.');
            }
            $indices = array_keys(array_filter($lines, fn ($l) => $l['tax_rate'] === $rate));
            $sum = 0;
            foreach ($indices as $i) {
                $lines[$i]['tax_cents'] = (int) floor($lines[$i]['net_cents'] * $rate / 100);
                $sum += $lines[$i]['tax_cents'];
            }
            $delta = $group['tax_cents'] - $sum;
            if ($delta < 0 || $delta > count($indices)) {
                $this->fail('Steuerrundung ist nicht plausibel.');
            }
            usort($indices, function ($a, $b) use ($lines, $rate) {
                $remainder = fn ($i) => $lines[$i]['net_cents'] * $rate - $lines[$i]['tax_cents'] * 100;

                return ($remainder($b) <=> $remainder($a)) ?: ($a <=> $b);
            });
            foreach (array_slice($indices, 0, $delta) as $i) {
                $lines[$i]['tax_cents']++;
            }
        }
        $evidence = [];
        foreach ($lines as $i => &$line) {
            $evidence[] = ['position_no' => $i + 1, ...$line['evidence']];
            unset($line['evidence']);
            $line['position_no'] = $i + 1;
            $line['gross_cents'] = $line['net_cents'] + $line['tax_cents'];
        }
        unset($line);
        $net = array_sum(array_column($groups, 'net_cents'));
        $tax = array_sum(array_column($groups, 'tax_cents'));
        if ($net !== $goodsTotal + $depositTotal || $net + $tax !== $gross) {
            $this->fail('Rechnungssummen stimmen nicht.');
        }

        return ['invoice_number' => $numbers[1][0], 'invoice_date' => $date->format('Y-m-d'),
            'currency' => 'EUR', 'net_cents' => $net, 'tax_cents' => $tax, 'gross_cents' => $gross,
            'lines' => $lines, 'confidence' => 100, 'validation_errors' => [],
            'parser_evidence' => ['parser' => 'gato_layout_v1', 'tax_groups' => $groups, 'positions' => $evidence,
                'deposits' => $deposits, 'deposit_tax_rate' => $depositRate, 'rounding' => 'largest_remainder']];
    }
}
