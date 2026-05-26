<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    protected $fillable = [
        'income_date',
        'source',
        'description',
        'amount',
        'notes',
        'receipt_path',
        'recorded_by_id',
    ];

    protected $casts = [
        'income_date' => 'date:Y-m-d',
        'amount'      => 'integer',
    ];

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}
