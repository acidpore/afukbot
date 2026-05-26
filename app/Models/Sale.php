<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number',
        'recipient_name',
        'recipient_address',
        'invoice_date',
        'notes',
        'sender_name',
        'sender_address',
        'attachment_path',
        'grand_total',
        'paid_amount',
        'status',
        'shipped_at',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
