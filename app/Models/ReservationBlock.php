<?php

namespace App\Models;

use App\Casts\ReservationDateTime;
use Illuminate\Database\Eloquent\Model;

class ReservationBlock extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['table_ids' => 'array', 'starts_at' => ReservationDateTime::class, 'ends_at' => ReservationDateTime::class];
}
