<?php

namespace App\Modules\Telegram;

use App\Models\Item;
use App\Models\StockTransaction;
use App\Modules\Inventory\InventoryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    private string $token;
    private string $apiUrl;

    public function __construct()
    {
        $this->token  = config('services.telegram.token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        Http::post("{$this->apiUrl}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
            return;
        }

        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$message) return;

        $chatId = $message['chat']['id'];
        $text   = trim($message['text'] ?? '');

        if (isset($message['voice'])) {
            $this->handleVoice($chatId, $message['voice']);
            return;
        }

        if (str_starts_with($text, '/stok')) {
            $keyword = trim(substr($text, 5));
            $this->handleStok($chatId, $keyword);
        } elseif (str_starts_with($text, '/valuasi')) {
            $this->handleValuasi($chatId);
        } elseif (str_starts_with($text, '/transaksi')) {
            $this->handleTransaksi($chatId);
        } elseif (str_starts_with($text, '/start') || str_starts_with($text, '/help')) {
            $this->handleHelp($chatId);
        } else {
            $this->sendMessage($chatId, "Perintah tidak dikenal. Ketik /help untuk melihat daftar perintah.");
        }
    }

    private function handleStok(int|string $chatId, string $keyword): void
    {
        $query = Item::with('category');

        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        $items = $query->orderBy('name')->get();

        if ($items->isEmpty()) {
            $this->sendMessage($chatId, "Tidak ada barang ditemukan" . ($keyword ? " untuk <b>{$keyword}</b>" : "") . ".");
            return;
        }

        $lines = ["<b>Stok Barang" . ($keyword ? " — {$keyword}" : "") . "</b>\n"];

        foreach ($items as $item) {
            $stok     = number_format($item->quantity, 0, ',', '.');
            $harga    = $item->harga_jual ? 'Rp ' . number_format($item->harga_jual, 0, ',', '.') : '-';
            $kategori = $item->category->name ?? '-';
            $lines[]  = "• <b>{$item->name}</b>\n  Stok: {$stok} {$item->unit} | Harga: {$harga}\n  <i>{$kategori}</i>";
        }

        $this->sendMessage($chatId, implode("\n\n", $lines));
    }

    private function handleValuasi(int|string $chatId): void
    {
        $items      = Item::with('category')->orderByDesc('quantity')->get();
        $totalNilai = $items->sum(fn($i) => $i->quantity * $i->harga_jual);
        $totalUnit  = $items->sum('quantity');

        $lines = ["<b>Valuasi Stok</b>", ""];

        foreach ($items as $item) {
            $nilai  = $item->quantity * $item->harga_jual;
            $stok   = number_format($item->quantity, 0, ',', '.');
            $harga  = $item->harga_jual ? 'Rp ' . number_format($item->harga_jual, 0, ',', '.') : '-';
            $nilaiF = $nilai ? 'Rp ' . number_format($nilai, 0, ',', '.') : '-';

            $lines[] = "<b>{$item->name}</b>"
                . "\n  Stok  : {$stok} {$item->unit}"
                . "\n  Harga : {$harga}"
                . "\n  Nilai : {$nilaiF}"
                . "\n";
        }

        $lines[] = "";
        $lines[] = str_repeat("─", 28);
        $lines[] = "Jenis Barang : <b>{$items->count()} item</b>";
        $lines[] = "Total Unit   : <b>" . number_format($totalUnit, 0, ',', '.') . " unit</b>";
        $lines[] = "Total Nilai  : <b>Rp " . number_format($totalNilai, 0, ',', '.') . "</b>";

        // Telegram max 4096 karakter per pesan — potong jika perlu
        $text    = implode("\n", $lines);
        $chunks  = mb_str_split($text, 4000);

        foreach ($chunks as $chunk) {
            $this->sendMessage($chatId, $chunk);
        }
    }

    private function handleTransaksi(int|string $chatId): void
    {
        $transactions = StockTransaction::with('item')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($transactions->isEmpty()) {
            $this->sendMessage($chatId, "Belum ada riwayat transaksi.");
            return;
        }

        $lines = ["<b>10 Transaksi Terakhir</b>\n"];

        foreach ($transactions as $t) {
            $arah    = $t->type === 'IN' ? '(+)' : '(-)';
            $qty     = number_format($t->quantity, 0, ',', '.');
            $nama    = $t->item->name ?? '-';
            $tgl     = \Carbon\Carbon::parse($t->created_at)->format('d M Y H:i');
            $catatan = $t->notes ? " — {$t->notes}" : '';
            $lines[] = "{$arah} <b>{$nama}</b> {$qty} {$t->item?->unit}\n  <i>{$tgl}{$catatan}</i>";
        }

        $this->sendMessage($chatId, implode("\n\n", $lines));
    }

    private function handleCallback(array $callback): void
    {
        $chatId     = $callback['message']['chat']['id'];
        $messageId  = $callback['message']['message_id'];
        $data       = $callback['data'];

        $this->answerCallback($callback['id']);

        if (!str_starts_with($data, 'adjust_')) return;

        // format: adjust_{token}_{itemId} atau adjust_{token}_all_{ids}
        $parts = explode('_', $data, 3); // ['adjust', token, rest]
        if (count($parts) < 3) return;

        $token   = $parts[1];
        $rest    = $parts[2]; // itemId atau "all_{ids}"
        $pending = Cache::get("tg_pending_{$token}");

        if (!$pending) {
            $this->sendMessage($chatId, "Sesi habis. Kirim ulang voice note.");
            return;
        }

        Cache::forget("tg_pending_{$token}");
        $this->editMessageText($chatId, $messageId, "Memproses...");

        if (str_starts_with($rest, 'all_')) {
            $ids   = explode(',', substr($rest, 4));
            $items = Item::whereIn('id', $ids)->get();
            foreach ($items as $item) {
                $this->applyStockAdjustment($chatId, $item, $pending['type'], $pending['quantity']);
            }
            return;
        }

        $item = Item::find((int) $rest);
        if (!$item) {
            $this->sendMessage($chatId, "Barang tidak ditemukan.");
            return;
        }

        $this->applyStockAdjustment($chatId, $item, $pending['type'], $pending['quantity']);
    }

    private function applyStockAdjustment(int|string $chatId, Item $item, string $type, int $quantity): void
    {
        try {
            $result  = app(InventoryService::class)->adjustStock($item->id, $type, $quantity, 'Via Voice Note Telegram');
            $arah    = $type === 'OUT' ? 'Keluar' : 'Masuk';
            $before  = number_format($result['transaction']->stock_before, 0, ',', '.');
            $after   = number_format($result['transaction']->stock_after, 0, ',', '.');
            $qty     = number_format($quantity, 0, ',', '.');

            $this->sendMessage($chatId,
                "<b>Stok diperbarui</b>\n\n"
                . "Barang  : <b>{$item->name}</b>\n"
                . "Aksi    : {$arah} {$qty} {$item->unit}\n"
                . "Sebelum : {$before} {$item->unit}\n"
                . "Sesudah : <b>{$after} {$item->unit}</b>"
            );
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "Gagal: {$e->getMessage()}");
        }
    }

    private function sendInlineKeyboard(int|string $chatId, string $text, array $buttons): void
    {
        Http::post("{$this->apiUrl}/sendMessage", [
            'chat_id'      => $chatId,
            'text'         => $text,
            'parse_mode'   => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    private function answerCallback(string $callbackId): void
    {
        Http::post("{$this->apiUrl}/answerCallbackQuery", ['callback_query_id' => $callbackId]);
    }

    private function editMessageText(int|string $chatId, int $messageId, string $text): void
    {
        Http::post("{$this->apiUrl}/editMessageText", [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    private function handleVoice(int|string $chatId, array $voice): void
    {
        $this->sendMessage($chatId, "Memproses voice note...");

        $transcript = $this->transcribeVoice($voice['file_id']);

        if (!$transcript) {
            $this->sendMessage($chatId, "Gagal mentranskripsi suara. Coba lagi.");
            return;
        }

        $corrected = $this->correctTranscript($transcript);
        $this->sendMessage($chatId, "Transkripsi: <i>\"{$transcript}\"</i>\n\nSedang memproses...");

        if ($this->isValuasiIntent($corrected)) {
            $this->handleValuasi($chatId);
            return;
        }

        $itemQuery = $this->detectItemQuery($corrected);
        if ($itemQuery !== null) {
            $this->handleItemQuery($chatId, $itemQuery);
            return;
        }

        $commands = $this->parseWithLLM($corrected);
        if (empty($commands)) {
            $single = $this->parseStockCommand($corrected);
            $commands = $single ? [$single] : [];
        }

        if (empty($commands)) {
            $this->sendMessage($chatId, "Tidak bisa memahami perintah. Pastikan menyebut:\n<b>Keluar/Masuk + nama barang + jumlah</b>\n\nContoh: <i>\"Keluar foodtray 2000\"</i> atau <i>\"Kompor keluar 4, wajan masuk 2\"</i>");
            return;
        }

        foreach ($commands as $parsed) {
            ['type' => $type, 'keyword' => $keyword, 'quantity' => $quantity] = $parsed;

            $items = $this->fuzzySearchItems($keyword);

            if ($items->isEmpty()) {
                $this->sendMessage($chatId, "Barang <b>\"{$keyword}\"</b> tidak ditemukan di database.");
                continue;
            }

            if ($items->count() === 1) {
                $this->applyStockAdjustment($chatId, $items->first(), $type, $quantity);
                continue;
            }

            $allIds  = $items->pluck('id')->implode(',');
            $arah    = $type === 'OUT' ? 'Keluar' : 'Masuk';
            $qty     = number_format($quantity, 0, ',', '.');

            // pakai token unik per keyboard supaya multi-command tidak saling overwrite
            $token   = uniqid();
            Cache::put("tg_pending_{$token}", ['type' => $type, 'quantity' => $quantity], now()->addMinutes(5));

            $buttons = $items->map(fn($item) => [[
                'text'          => $item->name . ' (stok: ' . $item->quantity . ' ' . $item->unit . ')',
                'callback_data' => "adjust_{$token}_{$item->id}",
            ]])->values()->toArray();

            if (str_contains(mb_strtolower($keyword), 'foodtray')) {
                $buttons[] = [[
                    'text'          => 'Keduanya',
                    'callback_data' => "adjust_{$token}_all_{$allIds}",
                ]];
            }

            $this->sendInlineKeyboard(
                $chatId,
                "Ditemukan <b>{$items->count()} barang</b> dengan kata kunci <b>\"{$keyword}\"</b>.\nPilih barang untuk <b>{$arah} {$qty}</b>:",
                $buttons
            );
        }
    }

    private function transcribeVoice(string $fileId): ?string
    {
        $token   = config('services.telegram.token');
        $fileRes = Http::get("https://api.telegram.org/bot{$token}/getFile", ['file_id' => $fileId]);
        $filePath = $fileRes->json('result.file_path');

        if (!$filePath) return null;

        $audioContent = Http::get("https://api.telegram.org/file/bot{$token}/{$filePath}")->body();
        $tmpPath = sys_get_temp_dir() . '/' . uniqid('vn_') . '.ogg';
        file_put_contents($tmpPath, $audioContent);

        $itemNames = Item::pluck('name')->implode(', ');
        $prompt    = "Perintah inventaris gudang MBG. Kata kunci: masuk, keluar, stok, {$itemNames}.";

        $res = Http::withToken(config('services.groq.key'))
            ->attach('file', fopen($tmpPath, 'r'), 'audio.ogg', ['Content-Type' => 'audio/ogg'])
            ->post('https://api.groq.com/openai/v1/audio/transcriptions', [
                'model'    => 'whisper-large-v3',
                'language' => 'id',
                'prompt'   => $prompt,
            ]);

        @unlink($tmpPath);

        return $res->json('text') ? trim($res->json('text')) : null;
    }

    private function fuzzySearchItems(string $keyword): \Illuminate\Support\Collection
    {
        $keyword     = mb_strtolower($keyword);
        $keywordNorm = preg_replace('/[^a-z0-9]/', '', $keyword);
        $allItems    = Item::all();
        $threshold   = 55;

        $scored = $allItems->map(function ($item) use ($keyword, $keywordNorm) {
            $nameLower = mb_strtolower($item->name);
            $nameNorm  = preg_replace('/[^a-z0-9]/', '', $nameLower);

            // cek alias dulu — alias match = skor tertinggi
            foreach ($item->aliases ?? [] as $alias) {
                $aliasNorm = preg_replace('/[^a-z0-9]/', '', mb_strtolower($alias));
                if (str_contains(mb_strtolower($alias), $keyword) || str_contains($keyword, mb_strtolower($alias))) {
                    return ['item' => $item, 'score' => 100];
                }
                similar_text($keywordNorm, $aliasNorm, $aliasPct);
                if ($aliasPct >= 70) return ['item' => $item, 'score' => 100];
            }

            // LIKE match
            if (str_contains($nameLower, $keyword) || str_contains($keyword, $nameLower)) {
                return ['item' => $item, 'score' => 100];
            }

            // similar_text pada nama asli
            similar_text($keyword, $nameLower, $pct1);

            // similar_text pada nama tanpa spasi/simbol
            similar_text($keywordNorm, $nameNorm, $pct2);

            // levenshtein
            $lev    = levenshtein($keywordNorm, $nameNorm);
            $maxLen = max(strlen($keywordNorm), strlen($nameNorm), 1);
            $pct3   = (1 - $lev / $maxLen) * 100;

            return ['item' => $item, 'score' => max($pct1, $pct2, $pct3)];
        })->filter(fn($r) => $r['score'] >= $threshold)
          ->sortByDesc('score');

        if ($scored->isEmpty()) return collect();

        $topScore = $scored->first()['score'];

        // kembalikan semua item yang skornya dalam range 15% dari top score
        return $scored->filter(fn($r) => $r['score'] >= $topScore - 15)
                      ->pluck('item')
                      ->values();
    }

    private function detectItemQuery(string $text): ?string
    {
        // cocokkan pola: "total [barang] X", "cek X", "stok X", "berapa X"
        $patterns = [
            '/\b(?:total barang|total stok|total|cek|stok|berapa)\s+(.+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $keyword = trim($m[1]);
                // pastikan bukan intent valuasi global
                if (!$this->isValuasiIntent($keyword)) {
                    return $keyword;
                }
            }
        }

        return null;
    }

    private function handleItemQuery(int|string $chatId, string $keyword): void
    {
        $items = $this->fuzzySearchItems($keyword);

        if ($items->isEmpty()) {
            $this->sendMessage($chatId, "Barang dengan kata kunci <b>\"{$keyword}\"</b> tidak ditemukan.");
            return;
        }

        $totalStok  = $items->sum('quantity');
        $totalNilai = $items->sum(fn($i) => $i->quantity * $i->harga_jual);
        $lines      = ["<b>Ringkasan: {$keyword}</b>\n"];

        foreach ($items as $item) {
            $nilai   = $item->quantity * $item->harga_jual;
            $stok    = number_format($item->quantity, 0, ',', '.');
            $nilaiF  = $nilai ? 'Rp ' . number_format($nilai, 0, ',', '.') : '-';
            $harga   = $item->harga_jual ? 'Rp ' . number_format($item->harga_jual, 0, ',', '.') : '-';
            $lines[] = "<b>{$item->name}</b>"
                . "\n  Stok  : {$stok} {$item->unit}"
                . "\n  Harga : {$harga}"
                . "\n  Nilai : {$nilaiF}";
        }

        $lines[] = "";
        $lines[] = str_repeat("─", 28);
        $lines[] = "Total Stok  : <b>" . number_format($totalStok, 0, ',', '.') . " unit</b>";
        $lines[] = "Total Nilai : <b>Rp " . number_format($totalNilai, 0, ',', '.') . "</b>";

        $this->sendMessage($chatId, implode("\n\n", $lines));
    }

    private function isValuasiIntent(string $text): bool
    {
        $keywords = ['total stok', 'total stock', 'valuasi', 'nilai stok', 'nilai stock', 'total nilai', 'harga stok', 'total barang'];
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) return true;
        }
        return false;
    }

    private function parseWithLLM(string $text): array
    {
        $itemNames = Item::pluck('name')->implode(', ');

        $prompt = "Kamu adalah parser perintah inventaris gudang MBG. "
            . "Daftar nama barang yang tersedia: {$itemNames}.\n\n"
            . "Tugas: baca kalimat berikut. Kalimat bisa mengandung SATU atau LEBIH perintah stok sekaligus.\n"
            . "Untuk setiap perintah, ekstrak:\n"
            . "- type: \"IN\" jika masuk/tambah/in/add, \"OUT\" jika keluar/kurang/out/lower/remove\n"
            . "- keyword: kata kunci umum nama barang (jika ada varian seperti 'Foodtray Badan' dan 'Foodtray Tutup', cukup tulis 'Foodtray')\n"
            . "- quantity: angka jumlah (integer)\n\n"
            . "Balas HANYA dengan JSON array, tanpa penjelasan.\n"
            . "Contoh 1 perintah : [{\"type\":\"OUT\",\"keyword\":\"Foodtray\",\"quantity\":1000}]\n"
            . "Contoh 2 perintah : [{\"type\":\"OUT\",\"keyword\":\"Kompor\",\"quantity\":4},{\"type\":\"IN\",\"keyword\":\"Wajan\",\"quantity\":2}]\n"
            . "Jika tidak bisa diparsing: [{\"error\":true}]\n\n"
            . "Kalimat: \"{$text}\"";

        $res = Http::withToken(config('services.groq.key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama-3.1-8b-instant',
                'temperature' => 0,
                'messages'    => [
                    ['role' => 'system', 'content' => 'Balas hanya dengan JSON array, tidak ada teks lain.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
            ]);

        $content = $res->json('choices.0.message.content');
        if (!$content) return [];

        preg_match('/\[.*\]/s', $content, $match);
        if (!$match) return [];

        $data = json_decode($match[0], true);
        if (!is_array($data)) return [];

        $result = [];
        foreach ($data as $item) {
            if (!empty($item['error']) || empty($item['keyword']) || empty($item['quantity'])) continue;
            $result[] = [
                'type'     => strtoupper($item['type'] ?? '') === 'OUT' ? 'OUT' : 'IN',
                'keyword'  => $item['keyword'],
                'quantity' => (int) $item['quantity'],
            ];
        }

        return $result;
    }

    private function correctTranscript(string $text): string
    {
        $corrections = [
            // koreksi kata arah
            'asok'        => 'masuk',
            'masukan'     => 'masuk',
            'kelurkan'    => 'keluar',
            'keluarkan'   => 'keluar',
            'kluar'       => 'keluar',
            'lower'       => 'keluar',
            'color'       => 'keluar',
            'kolor'       => 'keluar',
            'caluar'      => 'keluar',
            'kaluar'      => 'keluar',

            // koreksi foodtray / ompreng
            'foot tray'   => 'foodtray',
            'food tray'   => 'foodtray',
            'futray'      => 'foodtray',
            'fudtray'     => 'foodtray',
            'footrace'    => 'foodtray',
            'votre'       => 'foodtray',
            'foto tray'   => 'foodtray',
            'food tre'    => 'foodtray',
            'futri'       => 'foodtray',
            'futry'       => 'foodtray',
            'fudri'       => 'foodtray',
            'ompreng'       => 'foodtray',
            'om prank'      => 'foodtray',
            'omprank'       => 'foodtray',
            'om preng'      => 'foodtray',
            'om prenk'      => 'foodtray',
            'omprink'       => 'foodtray',
            'food pressure' => 'foodtray',
            'food press'    => 'foodtray',
            'food prices'   => 'foodtray',
            'foot pressure' => 'foodtray',

            // steamer
            'timmer'      => 'steamer',
            'timer'       => 'steamer',
            'temer'       => 'steamer',
            'stimer'      => 'steamer',

            // lainnya
            'loyang stainles' => 'loyang stainless',
            'wajan 80'    => 'wajan 80 cm',
            'wajan 60'    => 'wajan 60 cm',
        ];

        $lower = mb_strtolower($text);
        foreach ($corrections as $wrong => $correct) {
            $lower = str_replace($wrong, $correct, $lower);
        }

        return $lower;
    }

    private function convertNumberWords(string $text): string
    {
        $words = [
            'satu'          => 1,
            'dua'           => 2,
            'tiga'          => 3,
            'empat'         => 4,
            'lima'          => 5,
            'enam'          => 6,
            'tujuh'         => 7,
            'delapan'       => 8,
            'sembilan'      => 9,
            'sepuluh'       => 10,
            'sebelas'       => 11,
            'dua belas'     => 12,
            'lima belas'    => 15,
            'dua puluh'     => 20,
            'lima puluh'    => 50,
            'seratus'       => 100,
            'dua ratus'     => 200,
            'lima ratus'    => 500,
            'seribu'        => 1000,
            'dua ribu'      => 2000,
            'tiga ribu'     => 3000,
            'empat ribu'    => 4000,
            'lima ribu'     => 5000,
            'sepuluh ribu'  => 10000,
            'ribu'          => 1000,
            'ratus'         => 100,
        ];

        // urutkan dari yang terpanjang dulu agar "dua ribu" tidak dipotong jadi "dua" + "ribu"
        uksort($words, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($words as $word => $number) {
            $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/', $number, $text);
        }

        return $text;
    }

    private function parseStockCommand(string $text): ?array
    {
        $text = $this->correctTranscript(trim($text));
        $text = $this->convertNumberWords($text);
        $type = null;

        if (preg_match('/\b(keluar|kurang|kurangi|out)\b/', $text, $m)) {
            $type      = 'OUT';
            $dirWord   = $m[1];
        } elseif (preg_match('/\b(masuk|tambah|tambahkan|in)\b/', $text, $m)) {
            $type      = 'IN';
            $dirWord   = $m[1];
        }

        if (!$type) return null;

        // ambil semua angka, quantity = angka terakhir
        if (!preg_match_all('/\b(\d[\d.,]*)\b/', $text, $numMatches)) return null;
        $lastNum  = end($numMatches[1]);
        $quantity = (int) preg_replace('/[.,]/', '', $lastNum);

        // keyword = teks setelah kata arah, sebelum angka terakhir
        $afterDir = trim(preg_replace('/\b' . preg_quote($dirWord, '/') . '\b/', '', $text));
        $pos      = mb_strrpos($afterDir, $lastNum);
        $keyword  = $pos !== false ? trim(mb_substr($afterDir, 0, $pos)) : $afterDir;
        $keyword  = trim(preg_replace('/\s+/', ' ', $keyword));

        if (!$keyword || $quantity <= 0) return null;

        return ['type' => $type, 'keyword' => $keyword, 'quantity' => $quantity];
    }

    private function handleHelp(int|string $chatId): void
    {
        $text = "<b>MBG Inventory Bot</b>\n"
            . "Bot untuk memantau stok dan transaksi inventaris.\n\n"
            . "<b>Perintah tersedia:</b>\n\n"
            . "/stok — Tampilkan seluruh daftar barang beserta stok dan harga jual.\n\n"
            . "/stok [nama] — Cari barang. Contoh: <code>/stok foodtray</code>\n\n"
            . "/valuasi — Total nilai stok saat ini (qty x harga jual).\n\n"
            . "/transaksi — 10 transaksi stok terakhir.\n\n"
            . "<b>Voice Note</b>\n"
            . "Kirim voice note untuk update stok langsung.\n"
            . "Format: <i>\"Keluar [nama barang] [jumlah]\"</i>\n"
            . "Contoh: <i>\"Keluar foodtray 2000\"</i> atau <i>\"Masuk loyang 50\"</i>";

        $this->sendMessage($chatId, $text);
    }
}
