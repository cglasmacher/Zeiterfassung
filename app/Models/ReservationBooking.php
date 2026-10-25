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

    protected $casts = ['starts_at' => ReservationDateTime::class, 'ends_at' => ReservationDateTime::class, 'party_size' => 'integer', 'version' => 'integer'];

    public function tables()
    {
        return $this->belongsToMany(ReservationTable::class, 'reservation_booking_table', 'booking_id', 'table_id')->withTrashed();
    }
}
