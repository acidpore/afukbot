<?php

use App\Modules\Expense\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('expenses')->group(function () {
    Route::get('/',            [ExpenseController::class, 'index']);
    Route::post('/',           [ExpenseController::class, 'store']);
    Route::put('/{id}',        [ExpenseController::class, 'update']);
    Route::delete('/{id}',     [ExpenseController::class, 'destroy']);
    Route::get('/summary',     [ExpenseController::class, 'summary']);
    Route::post('/import',     [ExpenseController::class, 'import']);
});
