<?php

use App\Modules\Income\IncomeController;
use Illuminate\Support\Facades\Route;

Route::prefix('incomes')->group(function () {
    Route::get('/',        [IncomeController::class, 'index']);
    Route::post('/',       [IncomeController::class, 'store']);
    Route::put('/{id}',    [IncomeController::class, 'update']);
    Route::delete('/{id}', [IncomeController::class, 'destroy']);
    Route::post('/import',  [IncomeController::class, 'import']);
});
