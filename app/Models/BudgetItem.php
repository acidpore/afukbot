<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetItem extends Model
{
    protected $fillable = ['category_id', 'name', 'unit_cost', 'rate', 'multiplier', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'category_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ExpenseTransaction::class, 'budget_item_id');
    }
}
