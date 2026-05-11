<?php

use App\Modules\Sales\SalesController;
use Illuminate\Support\Facades\Route;

Route::prefix('sales')->group(function () {
    Route::get('/',             [SalesController::class, 'index']);
    Route::post('/',            [SalesController::class, 'store']);
    Route::get('/{id}',         [SalesController::class, 'show']);
    Route::put('/{id}',         [SalesController::class, 'update']);
    Route::patch('/{id}/pay',         [SalesController::class, 'pay']);
    Route::patch('/{id}/set-payment', [SalesController::class, 'setPayment']);
    Route::patch('/{id}/ship',  [SalesController::class, 'ship']);
    Route::delete('/{id}',      [SalesController::class, 'destroy']);
});
