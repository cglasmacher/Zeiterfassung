<?php

namespace App\Services\Accounting;

class HandelshofAccount
{
    public function resolve(array $line): ?array
    {
        $article = $line['article_number'] ?? '';
        $group = $line['invoice_group'] ?? '';
        $rate = (float) ($line['tax_rate'] ?? -1);
        // Specific identities precede broad invoice groups. Portal learning precedes this service.
        $account = match (true) {
            in_array($article, ['137654', '167506', '336937', '338564'], true) => '5131',
            $article === '315310' => '5134',
            // Confirmed: bar ice belongs to 5110; its invoice VAT remains 19%.
            $article === '127064' || ($group === 'Tiefkühlkost'
                && preg_match('/\b(?:voll)?eis[- ]?w(?:ü|ue)rfel\b/iu', $line['description'] ?? '')) => '5110',
            $group === 'Pfand/Leergut' => '1141',
            $group === 'Obst/Gemüse' => '5113',
            $group === 'Fleisch/Wurst/Geflügel' => '5111',
            $group === 'Fisch' => '5112',
            $group === 'Non-Food' => '6301',
            in_array($group, ['Feinkost/Mopro', 'Tiefkühlkost', 'Lebensmittel', 'Konserven'], true)
                && in_array($rate, [7.0, 19.0], true) => $rate === 7.0 ? '5110' : '5130',
            default => null,
        };

        return $account ? [$account, 'handelshof:'.$group.':'.$article] : null;
    }
}
