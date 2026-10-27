<?php

namespace App\Services\Accounting;

class GatoAccount
{
    public function resolve(array $line): ?array
    {
        // Confirmed Gato article identities; learned portal rules are evaluated first.
        $articles = [
            '04205' => '5133', '11388' => '5132', '20103' => '5131',
            '21004' => '5131', '21011' => '5131', '21012' => '5131',
            '22254' => '5131', '80400' => '5131',
        ];
        $article = trim((string) ($line['article_number'] ?? ''));
        if (isset($articles[$article])) {
            return [$articles[$article], 'gato:article:'.$article];
        }
        $description = mb_strtolower($line['description'] ?? '');
        $account = match (true) {
            str_contains($description, 'pfand'), str_contains($description, 'leergut') => '1141',
            str_contains($description, 'sirup') && (float) ($line['tax_rate'] ?? 0) === 7.0 => '5110',
            preg_match('/\b(bier|altbier|pils|weißbier|weissbier|weizen|fassbier|keg)\b/u', $description) === 1 => '5132',
            preg_match('/\b(wein|sekt|prosecco|frizzante)\b/u', $description) === 1 => '5133',
            preg_match('/\b(spirituosen|vodka|wodka|grappa)\b/u', $description) === 1 => '5134',
            preg_match('/\b(wasser|cola|saft|tonic|eistee|sprite)\b/u', $description) === 1 => '5131',
            default => null,
        };

        return $account ? [$account, 'gato:product:'.$account] : null;
    }
}
