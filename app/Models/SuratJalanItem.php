<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratJalanItem extends Model
{
    protected $fillable = [
        'surat_jalan_id',
        'sale_item_id',
        'qty_kirim',
    ];

    public function suratJalan()
    {
        return $this->belongsTo(SuratJalan::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
}
