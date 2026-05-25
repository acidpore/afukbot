<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app', ['page' => ['component' => 'Landing']]);
});

Route::get('/login', function () {
    return view('app', ['page' => ['component' => 'Login']]);
});

Route::get('/dashboard', function () {
    return view('app', ['page' => ['component' => 'Dashboard']]);
});


// Load Module Routes
require __DIR__.'/../app/Modules/Inventory/routes.php';
require __DIR__.'/../app/Modules/Employee/routes.php';
require __DIR__.'/../app/Modules/Attendance/routes.php';
require __DIR__.'/../app/Modules/Payroll/routes.php';
require __DIR__.'/../app/Modules/Telegram/routes.php';
require __DIR__.'/../app/Modules/Sales/routes.php';
require __DIR__.'/../app/Modules/Expense/routes.php';
require __DIR__.'/../app/Modules/Income/routes.php';

