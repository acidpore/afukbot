<?php

use App\Modules\Invoicing\CompanyController;
use App\Modules\Invoicing\InvoiceController;
use Illuminate\Support\Facades\Route;

// Companies
Route::get('/companies', [CompanyController::class, 'index']);
Route::post('/companies', [CompanyController::class, 'store']);
Route::put('/companies/{id}', [CompanyController::class, 'update']);
Route::delete('/companies/{id}', [CompanyController::class, 'destroy']);

// Invoices (multi-company, untuk audit pembelian luar)
Route::get('/invoices', [InvoiceController::class, 'index']);
Route::post('/invoices/preview', [InvoiceController::class, 'previewDraft']);
Route::post('/invoices', [InvoiceController::class, 'store']);
Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);
Route::get('/invoices/{id}/preview', [InvoiceController::class, 'preview']);
Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'pdf']);
