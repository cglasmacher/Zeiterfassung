<?php

namespace App\Services\Accounting;

use App\Models\AccountingDocument;

/** Accounting totals per document, account and tax rate; original article lines stay reviewable. */
class BookingSummary
{
    public function forDocument(AccountingDocument $document): array
    {
        $document->loadMissing('lines.account');
        $groups = [];
        $unresolved = 0;
        foreach ($document->lines as $line) {
            if (! $line->account?->active || $line->tax_rate === null
                || $line->net_cents === null || $line->tax_cents === null || $line->gross_cents === null) {
                $unresolved++;

                continue;
            }
            $rate = number_format((float) $line->tax_rate, 2, '.', '');
            $key = $line->account_id.':'.$rate;
            $groups[$key] ??= [
                'account_number' => $line->account->account_number, 'account_name' => $line->account->name,
                'tax_rate' => $rate, 'net_cents' => 0, 'tax_cents' => 0, 'gross_cents' => 0, 'line_count' => 0,
            ];
            foreach (['net_cents', 'tax_cents', 'gross_cents'] as $field) {
                $groups[$key][$field] += $line->{$field};
            }
            $groups[$key]['line_count']++;
        }
        usort($groups, fn ($a, $b) => strnatcmp($a['account_number'], $b['account_number']) ?: ((float) $a['tax_rate'] <=> (float) $b['tax_rate']));

        return ['rows' => array_values($groups), 'unresolved_lines' => $unresolved];
    }
}
