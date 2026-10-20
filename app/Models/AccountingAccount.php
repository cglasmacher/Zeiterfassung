<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    protected $fillable = ['account_number', 'name', 'kind', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function documentLines(): HasMany
    {
        return $this->hasMany(AccountingDocumentLine::class, 'account_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(AccountingRule::class, 'account_id');
    }
}
