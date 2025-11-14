<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeEntryAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'time_entry_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'note',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function timeEntry()
    {
        return $this->belongsTo(TimeEntry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}