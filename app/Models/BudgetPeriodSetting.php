<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetPeriodSetting extends Model
{
    protected $table    = 'budget_period_setting';
    protected $fillable = ['start_date', 'end_date'];
}
