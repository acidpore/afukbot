<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = [
        'expense_date',
        'category',
        'description',
        'amount',
        'paid_by',
        'notes',
        'receipt_path',
        'recorded_by_id',
        'expense_transaction_id',
    ];

    protected $casts = [
        'expense_date' => 'date:Y-m-d',
        'amount'       => 'integer',
    ];

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
