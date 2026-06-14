<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCalibration extends Model
{
    protected $fillable = ['calibrated_by', 'calibrated_at', 'notes', 'total_items', 'total_adjusted'];

    public function calibratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calibrated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCalibrationItem::class, 'calibration_id');
    }
}
