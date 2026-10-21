<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoReservation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime'];
}
