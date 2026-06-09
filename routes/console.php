<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto backup DB setiap hari jam 02:00 dini hari
Schedule::command('backup:upload')->dailyAt('02:00');

// Kirim reminder RAB setiap tanggal 1 jam 08:00
Schedule::command('rab:check-due')->monthlyOn(1, '08:00');

// Opsional: reminder pertengahan bulan (tanggal 15)
Schedule::command('rab:check-due')->monthlyOn(15, '08:00');
