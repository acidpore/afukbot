<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BackupAndUpload extends Command
{
    protected $signature   = 'backup:upload';
    protected $description = 'Backup database dan upload ke Google Drive via rclone';

    public function handle(): int
    {
        $this->info('Memulai backup database...');

        // 1. Jalankan spatie backup (DB only)
        $exitCode = $this->call('backup:run', ['--only-db' => true]);

        if ($exitCode !== 0) {
            $this->sendTelegram('❌ *Backup GAGAL!* Cek server segera.');
            return self::FAILURE;
        }

        $this->info('Backup selesai. Upload ke Google Drive...');

        // 2. Upload via rclone
        $localPath  = storage_path('app/' . config('app.name'));
        $remotePath = env('RCLONE_REMOTE', 'gdrive') . ':' . env('RCLONE_FOLDER', 'BackupAbsensi');

        exec("rclone sync \"{$localPath}\" \"{$remotePath}\" --log-level INFO 2>&1", $output, $rcloneCode);

        if ($rcloneCode !== 0) {
            $log = implode("\n", array_slice($output, -5));
            $this->sendTelegram("⚠️ *Backup OK tapi upload Google Drive GAGAL!*\n```{$log}```");
            $this->error('rclone gagal: ' . implode("\n", $output));
            return self::FAILURE;
        }

        $this->info('Upload berhasil!');
        $this->sendTelegram('✅ *Backup berhasil!* Database sudah tersimpan ke Google Drive.');

        return self::SUCCESS;
    }

    private function sendTelegram(string $message): void
    {
        $token  = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_ADMIN_CHAT_ID');

        if (!$token || !$chatId) return;

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'Markdown',
        ]);
    }
}
