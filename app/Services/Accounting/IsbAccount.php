<?php

namespace App\Services\Accounting;

class IsbAccount
{
    public function resolve(array $line): ?array
    {
        $article = $line['article_number'] ?? '';
        $confirmed = ['004179' => '5112', '004243' => '5112', '004430' => '5111', '004501' => '5111'];
        if (isset($confirmed[$article])) {
            return [$confirmed[$article], 'isb:article:'.$article];
        }
        $description = mb_strtolower($line['description'] ?? '');
        $fish = preg_match('/\b(garnelen?|lachsfilet|lachs|kabeljau|seelachs|dorade|wolfsbarsch|zander|thunfisch|tintenfisch|calamari|pulpo|shrimps?|scampi)\b/u', $description);
        $meat = preg_match('/\b(kalbs\pL*|rinder\pL*|rind|schweine\pL*|schwein|hähnchen\pL*|hänchen\pL*|haehnchen\pL*|puten\pL*|lamm\pL*|enten\pL*|ente)\b/u', $description);
        if ((bool) $fish === (bool) $meat) {
            return null;
        }
        $account = $fish ? '5112' : '5111';

        return [$account, 'isb:product:'.$account];
    }
}
