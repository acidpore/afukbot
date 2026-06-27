<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = ['budget_item_id', 'amount', 'transaction_date', 'note', 'receipt_path'];

    protected $casts = ['transaction_date' => 'date:Y-m-d'];

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class, 'budget_item_id')->withTrashed();
    }
}
