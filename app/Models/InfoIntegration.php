<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoIntegration extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['authorization', 'webhook_password'];

    protected $casts = ['enabled' => 'boolean', 'authorization' => 'encrypted', 'webhook_password' => 'encrypted', 'last_success_at' => 'datetime', 'last_attempt_at' => 'datetime'];

    public static function settings(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
