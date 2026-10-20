<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingExportBatch extends Model
{
    protected $fillable = [
        'status', 'filename', 'generated_by', 'generated_at', 'row_count', 'total_cents', 'metadata',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(AccountingDocument::class, 'export_batch_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
