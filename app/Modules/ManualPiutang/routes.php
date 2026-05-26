<?php

use App\Modules\ManualPiutang\ManualPiutangController;
use Illuminate\Support\Facades\Route;

Route::prefix('manual-piutang')->group(function () {
    Route::get('/',        [ManualPiutangController::class, 'index']);
    Route::post('/',       [ManualPiutangController::class, 'store']);
    Route::delete('/{id}', [ManualPiutangController::class, 'destroy']);
});
