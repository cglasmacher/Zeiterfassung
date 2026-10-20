<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingSupplier extends Model
{
    protected $fillable = ['name', 'creditor_account', 'aliases', 'active'];

    protected $casts = [
        'aliases' => 'array',
        'active' => 'boolean',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(AccountingDocument::class, 'supplier_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(AccountingRule::class, 'supplier_id');
    }
}
