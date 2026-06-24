<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMutation extends Model
{
    protected $fillable = ['bank_account_id', 'date', 'type', 'amount', 'description', 'category', 'costs', 'is_omzet'];

    protected $casts = ['costs' => 'array', 'is_omzet' => 'boolean'];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
