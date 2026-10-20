<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingRule extends Model
{
    protected $fillable = [
        'supplier_id', 'match_type', 'match_value', 'account_id', 'tax_rate',
        'priority', 'learned_from_manual', 'active',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'learned_from_manual' => 'boolean',
        'active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(AccountingSupplier::class, 'supplier_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id');
    }
}
