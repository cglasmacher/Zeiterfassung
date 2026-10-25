<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationTable extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'room_id', 'name', 'capacity', 'shape', 'x', 'y', 'width', 'height', 'rotation'];

    protected $casts = ['capacity' => 'integer', 'x' => 'integer', 'y' => 'integer', 'width' => 'integer', 'height' => 'integer', 'rotation' => 'integer'];
}
