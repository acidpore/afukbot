<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetup extends Command
{
    protected $signature   = 'telegram:setup';
    protected $description = 'Register bot commands dan deskripsi ke Telegram';

    public function handle(): void
    {
        $token = config('services.telegram.token');
        $base  = "https://api.telegram.org/bot{$token}";

        $commands = [
            ['command' => 'start',     'description' => 'Mulai & lihat semua perintah bot'],
            ['command' => 'help',      'description' => 'Tampilkan daftar perintah'],
            ['command' => 'stok',      'description' => 'Lihat semua stok barang'],
            ['command' => 'valuasi',   'description' => 'Total nilai stok saat ini'],
            ['command' => 'transaksi', 'description' => 'Lihat 10 transaksi terakhir'],
        ];

        $res = Http::post("{$base}/setMyCommands", ['commands' => $commands]);

        if ($res->json('ok')) {
            $this->info('Commands berhasil didaftarkan.');
        } else {
            $this->error('Gagal: ' . $res->body());
        }
    }
}
