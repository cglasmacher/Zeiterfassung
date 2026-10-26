<?php

namespace App\Models;

use App\Casts\ReservationDateTime;
use Illuminate\Database\Eloquent\Model;

class ReservationBooking extends Model
{
    public const ACTIVE = ['unconfirmed', 'confirmed', 'arrived', 'seated'];

    public const STATUSES = ['waiting', 'unconfirmed', 'confirmed', 'arrived', 'seated', 'finished', 'cancelled', 'noshow'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['public_request_key', 'public_request_hash'];

    protected $casts = ['confirmed_at' => ReservationDateTime::class, 'public_meta' => 'array', 'starts_at' => ReservationDateTime::class, 'ends_at' => ReservationDateTime::class, 'party_size' => 'integer', 'version' => 'integer', 'resmio_updated_at' => ReservationDateTime::class, 'resmio_synced_at' => ReservationDateTime::class, 'resmio_local_table_ids' => 'array', 'resmio_tags' => 'array', 'customer_tags' => 'array', 'resmio_tables' => 'array', 'resmio_data' => 'array', 'resmio_issues' => 'array', 'resmio_unassigned' => 'boolean', 'resmio_missing' => 'boolean'];

    // Keep legacy API/database values readable while exposing one arrival state.
    public function getStatusAttribute($value): string
    {
        return $value === 'arrived' ? 'seated' : $value;
    }

    public function getResmioLocalStatusAttribute($value): ?string
    {
        return $value === 'arrived' ? 'seated' : $value;
    }

    public function tables()
    {
        return $this->belongsToMany(ReservationTable::class, 'reservation_booking_table', 'booking_id', 'table_id')->withTrashed();
    }
}
