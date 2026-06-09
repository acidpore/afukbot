<?php

use App\Modules\SuratJalan\SuratJalanController;
use Illuminate\Support\Facades\Route;

Route::prefix('surat-jalan')->group(function () {
    Route::get('/',                    [SuratJalanController::class, 'index']);
    Route::get('/invoices-progress',   [SuratJalanController::class, 'invoicesWithProgress']);
    Route::get('/invoices-completed',  [SuratJalanController::class, 'completedInvoices']);
    Route::get('/by-sale/{saleId}',    [SuratJalanController::class, 'bySale']);
    Route::post('/',                   [SuratJalanController::class, 'store']);
    Route::delete('/{id}',             [SuratJalanController::class, 'destroy']);
});
