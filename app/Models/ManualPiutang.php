<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualPiutang extends Model
{
    protected $table = 'manual_piutang';
    protected $fillable = ['name', 'amount', 'notes'];
}
