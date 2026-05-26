<?php

namespace App\Console\Commands;

use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\ExpenseTransaction;
use App\Modules\Telegram\TelegramService;
use Illuminate\Console\Command;

class RabCheckDue extends Command
{
    protected $signature   = 'rab:check-due {--month= : Bulan target (format Y-m, default bulan ini)}';
    protected $description = 'Cek item RAB yang belum/kurang dibayar bulan ini dan kirim notifikasi Telegram';

    public function handle(TelegramService $telegram): void
    {
        $month  = $this->option('month') ?? now()->format('Y-m');
        $chatId = config('services.telegram.admin_chat_id');

        if (!$chatId) {
            $this->error('TELEGRAM_ADMIN_CHAT_ID belum diset di .env');
            return;
        }

        $this->info("Cek RAB bulan {$month}...");

        $categories = BudgetCategory::with(['items' => fn($q) => $q->where('is_active', true)])->get();

        $unpaidAll   = [];
        $totalKurang = 0;
        $totalPlan   = 0;
        $totalAktual = 0;

        foreach ($categories as $cat) {
            $catUnpaid = [];

            foreach ($cat->items as $item) {
                $paid   = ExpenseTransaction::where('budget_item_id', $item->id)
                    ->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$month])
                    ->sum('amount');
                $needed = $item->total_monthly_budget;

                $totalPlan   += $needed;
                $totalAktual += $paid;

                if ($paid < $needed) {
                    $sisa = $needed - $paid;
                    $totalKurang += $sisa;
                    $catUnpaid[] = [
                        'name'   => $item->name,
                        'needed' => $needed,
                        'paid'   => $paid,
                        'sisa'   => $sisa,
                    ];
                }
            }

            if ($catUnpaid) {
                $unpaidAll[$cat->name] = $catUnpaid;
            }
        }

        if (empty($unpaidAll)) {
            $msg = "✅ <b>RAB {$month}</b>\n\nSemua item sudah lunas. Total realisasi: <b>Rp " . number_format($totalAktual, 0, ',', '.') . "</b>";
            $telegram->sendMessage($chatId, $msg);
            $this->info('Semua item sudah lunas.');
            return;
        }

        // Susun pesan
        [$y, $m] = explode('-', $month);
        $bulanLabel = \Carbon\Carbon::createFromDate($y, $m, 1)->translatedFormat('F Y');

        $lines   = [];
        $lines[] = "🔔 <b>Reminder RAB — {$bulanLabel}</b>";
        $lines[] = "";

        foreach ($unpaidAll as $catName => $items) {
            $lines[] = "📁 <b>{$catName}</b>";
            foreach ($items as $item) {
                $needed = 'Rp ' . number_format($item['needed'], 0, ',', '.');
                $paid   = $item['paid'] > 0 ? 'Rp ' . number_format($item['paid'], 0, ',', '.') : 'belum ada';
                $sisa   = 'Rp ' . number_format($item['sisa'], 0, ',', '.');
                $lines[] = "  • <b>{$item['name']}</b>";
                $lines[] = "    Plan: {$needed} | Dibayar: {$paid}";
                $lines[] = "    <i>Kurang: {$sisa}</i>";
            }
            $lines[] = "";
        }

        $pct = $totalPlan > 0 ? round(($totalAktual / $totalPlan) * 100, 1) : 0;
        $lines[] = str_repeat("─", 28);
        $lines[] = "Total Plan    : Rp " . number_format($totalPlan, 0, ',', '.');
        $lines[] = "Total Realisasi: Rp " . number_format($totalAktual, 0, ',', '.');
        $lines[] = "Kurang Bayar  : <b>Rp " . number_format($totalKurang, 0, ',', '.') . "</b>";
        $lines[] = "Progress      : <b>{$pct}%</b>";

        $text = implode("\n", $lines);

        // Telegram max 4096 karakter
        foreach (mb_str_split($text, 4000) as $chunk) {
            $telegram->sendMessage($chatId, $chunk);
        }

        $this->info("Terkirim. {$totalKurang} total kurang bayar.");
        $this->table(
            ['Kategori', 'Item', 'Kurang'],
            collect($unpaidAll)->flatMap(fn($items, $cat) =>
                collect($items)->map(fn($i) => [$cat, $i['name'], 'Rp ' . number_format($i['sisa'], 0, ',', '.')])
            )->toArray()
        );
    }
}
