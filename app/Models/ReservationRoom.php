<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationRoom extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'width', 'height', 'revision', 'elements'];

    protected $casts = ['elements' => 'array', 'revision' => 'integer'];

    public function tables(): HasMany
    {
        return $this->hasMany(ReservationTable::class, 'room_id')->orderBy('name');
    }
}
