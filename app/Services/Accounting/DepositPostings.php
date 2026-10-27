<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingDocument;

/** Explicit net/VAT split. These entries must never also use an automatic tax key. */
class DepositPostings
{
    public function forGroups(AccountingDocument $document, array $groups): array
    {
        $document->loadMissing('supplier');
        $deposits = array_values(array_filter($groups, fn ($group) => $group['account_number'] === '1141'));
        if (! $deposits) {
            return ['rows' => [], 'errors' => []];
        }
        $errors = [];
        if (! $document->supplier?->active || ! $document->supplier?->creditor_account) {
            $errors[] = 'Pfandbuchung benötigt einen aktiven Kreditor.';
        }
        $hasTax = count(array_filter($deposits, fn ($row) => $row['tax_cents'] !== 0)) > 0;
        if ($hasTax && ! AccountingAccount::where('account_number', '1461')->where('active', true)->exists()) {
            $errors[] = 'Aktives Vorsteuerkonto 1461 für Pfand fehlt.';
        }
        foreach ($deposits as $group) {
            if ($group['gross_cents'] !== $group['net_cents'] + $group['tax_cents']) {
                $errors[] = 'Pfandnetto + Pfandvorsteuer entspricht nicht Pfandbrutto.';
            }
        }
        if ($errors) {
            return ['rows' => [], 'errors' => array_values(array_unique($errors))];
        }
        $rows = [];
        foreach ($deposits as $group) {
            foreach ([
                ['1141', 'Pfand netto', $group['net_cents'], false],
                ['1461', 'Vorsteuer Pfand', $group['tax_cents'], false],
                [$document->supplier->creditor_account, 'Kreditor Pfand brutto', $group['gross_cents'], true],
            ] as [$account, $label, $amount, $reverse]) {
                if ($amount === 0) {
                    continue;
                }
                $debit = ($amount > 0) !== $reverse;
                $rows[] = [
                    'account_number' => $account, 'label' => $label, 'tax_rate' => $group['tax_rate'],
                    'debit_cents' => $debit ? abs($amount) : 0,
                    'credit_cents' => $debit ? 0 : abs($amount),
                    'tax_mode' => 'explicit',
                ];
            }
        }

        return ['rows' => $rows, 'errors' => []];
    }
}
