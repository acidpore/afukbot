<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetPeriod extends Model
{
    protected $fillable = ['month', 'category_id', 'planned_amount', 'actual_amount', 'status'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'category_id');
    }
}
