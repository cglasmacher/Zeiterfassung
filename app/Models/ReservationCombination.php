<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationCombination extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['table_ids' => 'array', 'capacity' => 'integer'];
}
