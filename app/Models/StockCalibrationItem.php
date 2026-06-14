<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCalibrationItem extends Model
{
    protected $fillable = ['calibration_id', 'item_id', 'item_name', 'qty_system', 'qty_physical', 'delta'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
