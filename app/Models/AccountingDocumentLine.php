<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingDocumentLine extends Model
{
    protected $fillable = [
        'document_id', 'position_no', 'article_number', 'description', 'quantity', 'unit',
        'net_cents', 'tax_rate', 'tax_cents', 'gross_cents', 'account_id', 'account_number_snapshot',
        'classification_source', 'confidence', 'status', 'rule_key', 'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'tax_rate' => 'decimal:2',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'document_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id');
    }
}
