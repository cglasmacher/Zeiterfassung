<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoDisplay extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $casts = ['active' => 'boolean', 'show_guest_names' => 'boolean', 'last_seen_at' => 'datetime'];
}
