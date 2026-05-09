<?php

namespace App\Console\Commands;

use App\Modules\Telegram\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramPoll extends Command
{
    protected $signature   = 'telegram:poll';
    protected $description = 'Jalankan Telegram bot dengan long polling (untuk development lokal)';

    public function handle(TelegramService $telegramService): void
    {
        $token  = config('services.telegram.token');
        $base   = "https://api.telegram.org/bot{$token}";
        $offset = 0;

        $this->info('Bot berjalan... (Ctrl+C untuk berhenti)');

        while (true) {
            try {
                $res = Http::timeout(35)->get("{$base}/getUpdates", [
                    'offset'  => $offset,
                    'timeout' => 30,
                ]);

                $updates = $res->json('result') ?? [];

                foreach ($updates as $update) {
                    $offset = $update['update_id'] + 1;
                    $telegramService->handle($update);
                }
            } catch (\Exception $e) {
                $this->warn('Koneksi terputus, mencoba ulang...');
                sleep(3);
            }
        }
    }
}
