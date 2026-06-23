<?php

use App\Modules\Expense\ExpenseController;
use App\Modules\Expense\ReceiptScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('expenses')->group(function () {
    Route::post('/scan-receipt', [ReceiptScanController::class, 'scan']);
    Route::get('/',                      [ExpenseController::class, 'index']);
    Route::post('/',                     [ExpenseController::class, 'store']);
    Route::put('/{id}',                  [ExpenseController::class, 'update']);
    Route::delete('/{id}',               [ExpenseController::class, 'destroy']);
    Route::get('/summary',               [ExpenseController::class, 'summary']);
    Route::post('/import',               [ExpenseController::class, 'import']);
    Route::post('/{id}/receipt',         [ExpenseController::class, 'uploadReceipt']);
    Route::delete('/{id}/receipt',       [ExpenseController::class, 'deleteReceipt']);
});
