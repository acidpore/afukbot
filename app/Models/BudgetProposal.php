<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetProposal extends Model
{
    protected $fillable = ['period_id', 'name', 'brand', 'price', 'note', 'analysis', 'status', 'bought_at'];

    protected $casts = [
        'analysis'   => 'array',
        'bought_at'  => 'date:Y-m-d',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(RabPeriod::class, 'period_id');
    }
}
