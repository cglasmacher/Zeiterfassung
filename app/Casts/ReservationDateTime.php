<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ReservationDateTime implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC')->setTimezone('Europe/Berlin');
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'Europe/Berlin')->utc()->format('Y-m-d H:i:s');
    }
}
