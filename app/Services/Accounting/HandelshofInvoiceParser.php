<?php

namespace App\Services\Accounting;

use DOMDocument;
use DOMNode;
use DOMXPath;
use RuntimeException;

/** Supplier-specific Factur-X data, cross-checked against every printed position and tax group. */
class HandelshofInvoiceParser
{
    private DOMXPath $xpath;

    private function fail(string $reason): never
    {
        throw new RuntimeException('Handelshof-Analyse: '.$reason);
    }

    private function nodes(string $path, ?DOMNode $node = null): array
    {
        return iterator_to_array($this->xpath->query($path, $node));
    }

    private function value(string $path, ?DOMNode $node = null): string
    {
        $nodes = $this->nodes($path, $node);
        if (count($nodes) !== 1) {
            $this->fail('XML-Feld fehlt oder ist mehrfach vorhanden: '.$path);
        }

        return trim($nodes[0]->textContent);
    }

    private function decimal(string $value): float
    {
        if (! preg_match('/^-?\d{1,6}(?:\.\d{1,4})?$/D', $value)) {
            $this->fail('Ungültige Menge oder Preisangabe.');
        }

        return (float) $value;
    }

    private function cents(string $value): int
    {
        if (! preg_match('/^-?\d{1,9}\.\d{2}$/D', $value)) {
            $this->fail('Ungültiger Geldbetrag.');
        }

        return (int) str_replace('.', '', $value);
    }

    private function printedCents(string $value): int
    {
        return $this->cents(str_replace(['.', ','], ['', '.'], $value));
    }

    public function parse(string $xml, string $layout): array
    {
        if (strlen($xml) > 2000000 || preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i', $xml)) {
            $this->fail('XML zu groß oder enthält nicht unterstützte Deklarationen.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            if (! $dom->loadXML($xml, LIBXML_NONET)) {
                $this->fail('Ungültiges Rechnungs-XML.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $this->xpath = new DOMXPath($dom);
        foreach ([
            'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
            'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
            'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
        ] as $prefix => $uri) {
            $this->xpath->registerNamespace($prefix, $uri);
        }
        $root = '/rsm:CrossIndustryInvoice';
        $transaction = $root.'/rsm:SupplyChainTradeTransaction';
        $header = $transaction.'/ram:ApplicableHeaderTradeSettlement';
        $seller = $transaction.'/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty';
        if (! str_contains($this->value($seller.'/ram:Name'), 'Handelshof Köln')
            || $this->value($seller.'/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]') !== 'DE123046183'
            || $this->value($root.'/rsm:ExchangedDocument/ram:TypeCode') !== '380'
            || $this->value($header.'/ram:InvoiceCurrencyCode') !== 'EUR') {
            $this->fail('Lieferant, Rechnungsart oder Währung wird von diesem Profil nicht unterstützt.');
        }
        if ($this->nodes('//ram:SpecifiedTradeAllowanceCharge | //ram:AppliedTradeAllowanceCharge')) {
            $this->fail('Zusätzliche Zu-/Abschläge benötigen eine gesonderte Prüfung.');
        }
        $invoice = $this->value($root.'/rsm:ExchangedDocument/ram:ID');
        $rawDate = $this->value($root.'/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString[@format="102"]');
        $date = \DateTimeImmutable::createFromFormat('!Ymd', $rawDate);
        if ($invoice === '' || mb_strlen($invoice) > 255 || ! $date || $date->format('Ymd') !== $rawDate) {
            $this->fail('Rechnungsnummer oder Rechnungsdatum ungültig.');
        }
        $groups = [];
        foreach ($this->nodes($header.'/ram:ApplicableTradeTax') as $node) {
            $rate = $this->taxRate($node);
            if (isset($groups[$rate])) {
                $this->fail('Doppelte Steuergruppe.');
            }
            $net = $this->cents($this->value('ram:BasisAmount', $node));
            $tax = $this->cents($this->value('ram:CalculatedAmount', $node));
            if ((int) round($net * $rate / 100) !== $tax) {
                $this->fail('Steuergruppe ist rechnerisch nicht plausibel.');
            }
            $groups[$rate] = ['net_cents' => $net, 'tax_cents' => $tax];
        }
        if (! $groups) {
            $this->fail('Steuergruppen fehlen.');
        }
        $totals = $header.'/ram:SpecifiedTradeSettlementHeaderMonetarySummation';
        $net = $this->cents($this->value($totals.'/ram:TaxBasisTotalAmount'));
        $tax = $this->cents($this->value($totals.'/ram:TaxTotalAmount[@currencyID="EUR"]'));
        $gross = $this->cents($this->value($totals.'/ram:GrandTotalAmount'));
        foreach (['ChargeTotalAmount', 'AllowanceTotalAmount', 'RoundingAmount', 'TotalPrepaidAmount'] as $field) {
            if ($this->cents($this->value($totals.'/ram:'.$field)) !== 0) {
                $this->fail('Zusatzbeträge oder Vorauszahlungen benötigen eine gesonderte Prüfung.');
            }
        }
        if ($net !== $this->cents($this->value($totals.'/ram:LineTotalAmount'))
            || $gross !== $this->cents($this->value($totals.'/ram:DuePayableAmount'))
            || $net !== array_sum(array_column($groups, 'net_cents'))
            || $tax !== array_sum(array_column($groups, 'tax_cents')) || $net + $tax !== $gross) {
            $this->fail('Rechnungssummen widersprechen sich.');
        }

        $lines = [];
        $positions = [];
        foreach ($this->nodes($transaction.'/ram:IncludedSupplyChainTradeLineItem') as $node) {
            $id = $this->value('ram:AssociatedDocumentLineDocument/ram:LineID', $node);
            if (! preg_match('/^[1-9]\d{0,4}$/D', $id) || isset($positions[$id])) {
                $this->fail('Doppelte oder ungültige Positionsnummer.');
            }
            $product = 'ram:SpecifiedTradeProduct/';
            $article = $this->value($product.'ram:SellerAssignedID', $node);
            $name = $this->value($product.'ram:Name', $node);
            $character = $product.'ram:ApplicableProductCharacteristic';
            $group = $this->value($character.'[ram:Description="RECHNUNGSGRUPPE"]/ram:Value', $node);
            $unit = $this->value($character.'[ram:Description="VERPACKUNGSEINHEIT"]/ram:Value', $node);
            $marker = $this->value($character.'[ram:Description="MEHRWERTSSTEUERKENNZEICHEN"]/ram:Value', $node);
            $settlement = 'ram:SpecifiedLineTradeSettlement/';
            $rateNodes = $this->nodes($settlement.'ram:ApplicableTradeTax', $node);
            if (count($rateNodes) !== 1) {
                $this->fail('Positionssteuer fehlt oder ist unklar.');
            }
            $rate = $this->taxRate($rateNodes[0]);
            $amount = $this->cents($this->value($settlement.'ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount', $node));
            $quantity = $this->decimal($this->value('ram:SpecifiedLineTradeDelivery/ram:BilledQuantity', $node));
            $pricePath = 'ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/';
            $price = $this->decimal($this->value($pricePath.'ram:ChargeAmount', $node));
            $pack = $this->decimal($this->value($pricePath.'ram:BasisQuantity', $node));
            if (! preg_match('/^\d{6}$/D', $article) || $name === '' || mb_strlen($unit) > 20
                || ! isset($groups[$rate]) || $marker !== (string) ($rate === 19 ? 1 : 2)
                || $pack <= 0 || $price < 0 || abs((int) round($quantity * $pack * $price * 100) - $amount) > 1
                || ($quantity == 0 && $amount !== 0)) {
                $this->fail('Artikel, Steuerkennzeichen oder Menge × Inhalt × Preis ist widersprüchlich: '.$id);
            }
            $positions[$id] = ['article_number' => $article, 'description' => $name, 'invoice_group' => $group,
                'net_cents' => $amount, 'tax_rate' => $rate, 'quantity' => $quantity, 'unit' => $unit,
                'pack' => $pack, 'price' => $price, 'marker' => $marker];
            if ($amount !== 0) {
                $lines[] = ['position_no' => (int) $id, 'article_number' => $article, 'description' => $name,
                    'invoice_group' => $group, 'quantity' => $quantity, 'unit' => $unit,
                    'net_cents' => $amount, 'tax_rate' => $rate, 'confidence' => 100];
            }
        }
        if (! $lines || count($positions) > 1000) {
            $this->fail('Positionen fehlen oder sind zu umfangreich.');
        }
        $this->checkPrinted($layout, $invoice, $date->format('d.m.Y'), $positions, $groups, $gross);

        // Allocate only the printed group VAT, preserving signs and exact group totals.
        foreach ($groups as $rate => $group) {
            $indices = array_keys(array_filter($lines, fn ($line) => $line['tax_rate'] === $rate));
            if (array_sum(array_map(fn ($i) => $lines[$i]['net_cents'], $indices)) !== $group['net_cents']) {
                $this->fail('Positionssumme stimmt nicht mit der XML-Steuergruppe überein.');
            }
            foreach ($indices as $i) {
                $lines[$i]['tax_cents'] = (int) floor($lines[$i]['net_cents'] * $rate / 100);
            }
            $delta = $group['tax_cents'] - array_sum(array_map(fn ($i) => $lines[$i]['tax_cents'], $indices));
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
        foreach ($lines as &$line) {
            $line['gross_cents'] = $line['net_cents'] + $line['tax_cents'];
        }
        unset($line);

        return ['invoice_number' => $invoice, 'invoice_date' => $date->format('Y-m-d'), 'currency' => 'EUR',
            'net_cents' => $net, 'tax_cents' => $tax, 'gross_cents' => $gross, 'confidence' => 100,
            'validation_errors' => [], 'lines' => $lines,
            'parser_evidence' => ['parser' => 'handelshof_facturx_v1', 'tax_groups' => $groups,
                'positions' => $positions, 'rounding' => 'largest_remainder', 'pdf_cross_check' => true]];
    }

    private function taxRate(DOMNode $node): int
    {
        $rate = $this->value('ram:RateApplicablePercent', $node);
        if ($this->value('ram:TypeCode', $node) !== 'VAT' || $this->value('ram:CategoryCode', $node) !== 'S'
            || ! in_array($rate, ['7.00', '19.00'], true)) {
            $this->fail('Nicht unterstützte oder fehlende Umsatzsteuer.');
        }

        return (int) $rate;
    }

    private function checkPrinted(string $layout, string $invoice, string $date, array $positions, array $groups, int $gross): void
    {
        preg_match_all('/Rechnungs-Nr\.\s*:\s*(\S+)\s+Rechnungsdatum\s*:\s*(\d{2}\.\d{2}\.\d{4})\s+Seite:\s*(\d+)/u', $layout, $headers, PREG_SET_ORDER);
        if (! $headers || array_column($headers, 1) !== array_fill(0, count($headers), $invoice)
            || array_column($headers, 2) !== array_fill(0, count($headers), $date)
            || array_map('intval', array_column($headers, 3)) !== range(1, count($headers))) {
            $this->fail('XML und PDF widersprechen sich bei Nummer, Datum oder Seitenfolge.');
        }
        $money = '-?\d+(?:\.\d{3})*,\d{2}';
        $seen = [];
        $currentGroup = null;
        $groupNames = array_unique(array_column($positions, 'invoice_group'));
        foreach (explode("\n", $layout) as $row) {
            if (in_array(trim($row), $groupNames, true)) {
                $currentGroup = trim($row);
            }
            if (! preg_match('/^\s*(\d+)\s+(\d{6})\s+(.+?)\s+([A-Z]{2,3})\s+(\d+(?:,\d+)?)\s+(-?\d+(?:,\d+)?)\s+(\d+,\d{3})\s+('.$money.')\s+([12])(?:\s+[A-Z])?\s*$/u', $row, $m)) {
                continue;
            }
            $id = $m[1];
            $position = $positions[$id] ?? null;
            $normalize = fn ($v) => preg_replace('/\s+/u', ' ', trim($v));
            if (! $position || isset($seen[$id]) || $position['article_number'] !== $m[2]
                || $position['invoice_group'] !== $currentGroup
                || $normalize($position['description']) !== $normalize($m[3]) || $position['unit'] !== $m[4]
                || $position['pack'] !== $this->decimal(str_replace(',', '.', $m[5]))
                || $position['quantity'] !== $this->decimal(str_replace(',', '.', $m[6]))
                || abs($position['price'] - $this->decimal(str_replace(',', '.', $m[7]))) > 0.00001
                || $position['net_cents'] !== $this->printedCents($m[8]) || $position['marker'] !== $m[9]) {
                $this->fail('XML und gedruckte Position widersprechen sich: '.$id);
            }
            $seen[$id] = true;
        }
        if (count($seen) !== count($positions)) {
            $this->fail('Nicht alle XML-Positionen sind vollständig im PDF belegt.');
        }
        preg_match_all('/^\s*\d+\s+('.$money.')\s+('.$money.')\s+('.$money.')\s+\(\s*(19|7),0%=([12])\)\s+('.$money.')/mu', $layout, $rows, PREG_SET_ORDER);
        $seenRates = [];
        foreach ($rows as $m) {
            $rate = (int) $m[4];
            if (isset($seenRates[$rate]) || ! isset($groups[$rate])
                || $this->printedCents($m[1]) + $this->printedCents($m[2]) !== $this->printedCents($m[3])
                || $groups[$rate]['net_cents'] !== $this->printedCents($m[3])
                || $groups[$rate]['tax_cents'] !== $this->printedCents($m[6])
                || $m[5] !== (string) ($rate === 19 ? 1 : 2)) {
                $this->fail('XML und gedruckte Steuergruppe widersprechen sich.');
            }
            $deposit = array_sum(array_column(array_filter($positions, fn ($p) => $p['invoice_group'] === 'Pfand/Leergut' && $p['tax_rate'] === $rate), 'net_cents'));
            if ($deposit !== $this->printedCents($m[2])) {
                $this->fail('Pfandsumme stimmt nicht mit dem Original überein.');
            }
            $seenRates[$rate] = true;
        }
        preg_match_all('/Der Rechnungsbetrag\s*\(EUR\s+('.$money.')\)/u', $layout, $printedGross);
        if (count($seenRates) !== count($groups) || count($printedGross[1]) !== 1
            || $this->printedCents($printedGross[1][0]) !== $gross) {
            $this->fail('Rechnungsabschluss fehlt oder stimmt nicht mit dem XML überein.');
        }
    }
}
