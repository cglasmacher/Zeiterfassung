<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingDocument extends Model
{
    protected $fillable = [
        'source', 'source_reference', 'supplier_id', 'supplier_name_raw',
        'invoice_number', 'invoice_date', 'service_date_from', 'service_date_to',
        'currency', 'net_cents', 'tax_cents', 'gross_cents', 'confidence', 'status',
        'original_filename', 'file_path', 'raw_payload', 'validation_errors',
        'approved_by', 'approved_at', 'export_batch_id', 'exported_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'service_date_from' => 'date',
        'service_date_to' => 'date',
        'raw_payload' => 'array',
        'validation_errors' => 'array',
        'approved_at' => 'datetime',
        'exported_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(AccountingSupplier::class, 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingDocumentLine::class, 'document_id')->orderBy('position_no')->orderBy('id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function exportBatch(): BelongsTo
    {
        return $this->belongsTo(AccountingExportBatch::class, 'export_batch_id');
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['approved', 'exported'], true)
            || $this->approved_at !== null || $this->exported_at !== null
            || $this->export_batch_id !== null;
    }

    public function needsReview(): bool
    {
        return ! empty(app(\App\Services\Accounting\DocumentValidation::class)->errors($this));
    }
}
