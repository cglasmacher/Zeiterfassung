<?php

namespace App\Services\Cash;

use Illuminate\Validation\ValidationException;

class CashCalculator
{
    // All cash arithmetic uses integer cents. Decimal hours are converted to minutes.
    public static function cents(mixed $value): int
    {
        $value = str_replace(',', '.', (string) $value);
        if (! preg_match('/^-?\d{1,9}(\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['amount' => 'Geldbetrag mit höchstens zwei Nachkommastellen erforderlich.']);
        }
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-')), 2, '');

        return ((int) $whole * 100 + (int) str_pad($fraction, 2, '0')) * ($negative ? -1 : 1);
    }

    public static function calculate(int $opening, int $cashDue, array $movements, array $workers, int $counted, int $envelope, int $target): array
    {
        $expenses = array_sum(array_column($movements, 'amount_cents'));
        $paid = array_sum(array_map(fn ($worker) => $worker['paid_cash'] ? $worker['amount_cents'] : 0, $workers));
        $expected = $opening + $cashDue - $expenses - $paid;
        if ($counted < 0 || $envelope < 0 || $envelope > $counted) {
            throw ValidationException::withMessages(['envelope' => 'Umschlagbetrag darf den gezählten Bestand nicht übersteigen.']);
        }
        $remaining = $counted - $envelope;

        return [
            'opening_cents' => $opening, 'cash_due_cents' => $cashDue,
            'movements_cents' => $expenses, 'paid_cents' => $paid,
            'expected_cents' => $expected, 'counted_cents' => $counted,
            'difference_cents' => $counted - $expected, 'envelope_cents' => $envelope,
            'suggested_envelope_cents' => max(0, $counted - $target),
            'remaining_cents' => $remaining, 'float_target_cents' => $target,
            'top_up_cents' => max(0, $target - $remaining),
        ];
    }
}
