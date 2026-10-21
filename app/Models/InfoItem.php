<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoItem extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['file_path'];

    protected $casts = ['active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
