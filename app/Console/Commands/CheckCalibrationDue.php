<?php

namespace App\Console\Commands;

use App\Models\StockCalibration;
use App\Services\WebPushService;
use Illuminate\Console\Command;

class CheckCalibrationDue extends Command
{
    protected $signature   = 'calibration:check';
    protected $description = 'Kirim push notifikasi ke super admin jika kalibrasi stok sudah jatuh tempo';

    public function handle(WebPushService $push): void
    {
        $last      = StockCalibration::latest('calibrated_at')->first();
        $daysSince = $last ? now()->diffInDays($last->calibrated_at) : null;

        if ($daysSince === null || $daysSince >= 7) {
            $body = $last
                ? "Sudah {$daysSince} hari sejak kalibrasi terakhir. Lakukan sekarang agar data stok akurat."
                : "Kalibrasi stok belum pernah dilakukan. Segera lakukan untuk memastikan data akurat.";

            $push->sendToSuperAdmins('Kalibrasi Stok Jatuh Tempo', $body, '/dashboard?tab=inventory-calibration');
            $this->info('Push notifikasi kalibrasi dikirim.');
        } else {
            $this->info("Kalibrasi masih oke ({$daysSince} hari yang lalu). Tidak ada notifikasi dikirim.");
        }
    }
}
