<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'item_name',
        'description',
        'qty',
        'unit_price',
        'total_price',
        'inventory_item_ids',
        'is_online_order',
    ];

    protected $casts = [
        'inventory_item_ids' => 'array',
        'is_online_order'    => 'boolean',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
