<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationTag extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['normalized_name'];

    protected $casts = ['active' => 'boolean'];
}
