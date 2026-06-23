<?php

namespace App\Modules\MutasiRekening;

use App\Http\Controllers\Controller;
use App\Models\AccountMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TaxConsultantController extends Controller
{
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL    = 'llama-3.3-70b-versatile';

    public function chat(Request $request)
    {
        $request->validate([
            'message'         => 'required|string|max:1000',
            'history'         => 'nullable|array|max:20',
            'history.*.role'  => 'required|in:user,assistant',
            'history.*.text'  => 'required|string',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'year'            => 'required|integer',
        ]);

        $apiKey = config('services.groq.key');
        if (!$apiKey) {
            return response()->json(['message' => 'GROQ_API_KEY belum dikonfigurasi'], 500);
        }

        $context      = $this->buildContext($request->integer('bank_account_id'), $request->integer('year'));
        $systemPrompt = $this->buildSystemPrompt($context);

        $messages   = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        foreach ($request->input('history', []) as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['text']];
        }

        $messages[] = ['role' => 'user', 'content' => $request->input('message')];

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post(self::GROQ_URL, [
                'model'       => self::MODEL,
                'messages'    => $messages,
                'temperature' => 0.4,
                'max_tokens'  => 1024,
            ]);

        if (!$response->successful()) {
            \Log::error('Groq chat failed', ['status' => $response->status(), 'body' => $response->body()]);

            if ($response->status() === 429) {
                $retryAfter = (int) ($response->header('retry-after')
                    ?? $response->json('error.message') && preg_match('/(\d+(\.\d+)?)s/', $response->json('error.message', ''), $m) ? ceil((float)$m[1]) : 60);

                $wait = $retryAfter >= 60
                    ? round($retryAfter / 60) . ' menit'
                    : $retryAfter . ' detik';

                return response()->json([
                    'message'     => 'Quota AI habis.',
                    'retry_after' => $retryAfter,
                    'wait'        => $wait,
                ], 429);
            }

            return response()->json(['message' => 'Gagal menghubungi AI. Coba lagi.'], 502);
        }

        $text = $response->json('choices.0.message.content', '');

        return response()->json(['reply' => trim($text)]);
    }

    private function buildContext(int $bankAccountId, int $year): array
    {
        $yearly = AccountMutation::where('bank_account_id', $bankAccountId)
            ->whereNotIn('type', ['opening'])
            ->whereYear('date', $year)
            ->get();

        $totalIn       = $yearly->where('type', 'in')->sum('amount');
        $totalOut      = $yearly->where('type', 'out')->sum('amount');
        $variableCosts = $yearly->where('type', 'in')->sum(fn($m) => collect($m->costs ?? [])->sum('amount'));
        $netProfit     = $totalIn - $totalOut - $variableCosts;
        $limit         = 4_800_000_000;
        $overLimit     = $totalIn >= $limit;
        $pphFinal      = round($totalIn * 0.005);
        $pphBadan      = $netProfit > 0 ? round($netProfit * 0.22) : 0;

        $deductibleKw    = ['gaji', 'sewa', 'listrik', 'air', 'internet', 'telepon', 'operasional', 'supplier',
                            'bahan', 'atk', 'transport', 'bensin', 'bbm', 'marketing', 'iklan', 'pembelian',
                            'servis', 'perbaikan', 'cetak', 'pengiriman', 'kirim', 'kuli', 'jasa'];
        $nonDeductibleKw = ['tarik tunai', 'pribadi', 'prive', 'personal', 'transfer pribadi', 'tunai'];

        $categories = $yearly->where('type', 'out')
            ->groupBy('category')
            ->map(fn($rows, $cat) => [
                'kategori' => $cat ?: 'Tanpa Kategori',
                'total'    => $rows->sum('amount'),
                'status'   => (function() use ($cat, $deductibleKw, $nonDeductibleKw) {
                    $lower = strtolower($cat ?? '');
                    foreach ($nonDeductibleKw as $kw) { if (str_contains($lower, $kw)) return 'non-deductible'; }
                    foreach ($deductibleKw    as $kw) { if (str_contains($lower, $kw)) return 'deductible'; }
                    return 'perlu review';
                })(),
            ])
            ->values()
            ->toArray();

        $monthNames   = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $activeMonths = $yearly->pluck('date')
            ->map(fn($d) => (int) date('n', strtotime($d)))
            ->unique()
            ->sort()
            ->values();
        $monthsPassed    = max(1, $activeMonths->count());
        $activeMonthNames = $activeMonths->map(fn($m) => $monthNames[$m - 1])->implode(', ');

        return compact(
            'year', 'totalIn', 'totalOut', 'variableCosts', 'netProfit',
            'overLimit', 'pphFinal', 'pphBadan', 'limit', 'categories', 'monthsPassed', 'activeMonthNames'
        );
    }

    private function buildSystemPrompt(array $c): string
    {
        $fmt = fn(int $n) => 'Rp ' . number_format($n, 0, ',', '.');
        $regime = $c['overLimit'] ? 'PPh Badan 22%' : 'PPh Final UMKM 0,5%';

        $catLines = collect($c['categories'])->map(
            fn($cat) => "  - {$cat['kategori']}: {$fmt($cat['total'])} ({$cat['status']})"
        )->implode("\n");

        return <<<PROMPT
Kamu adalah konsultan pajak profesional Indonesia yang membantu klien memahami kewajiban pajak dan keuangan bisnis mereka.
Gunakan bahasa Indonesia yang santai tapi tetap akurat. Jawab ringkas, maksimal 3-4 kalimat. Langsung ke inti — tidak perlu mengulang pertanyaan, tidak perlu disclaimer panjang, tidak perlu menyebut asumsi lebih dari sekali.

Aturan penting:
- Jika data tersedia, gunakan untuk menjawab langsung.
- Jika diminta proyeksi atau estimasi, SELALU berikan perhitungan berdasarkan data yang ada (rata-rata bulanan, tren, dll) meski datanya terbatas. Sertakan asumsi yang dipakai.
- Saat menjawab pertanyaan tentang proyeksi, pahami konteks pertanyaan: apakah klien menanya omzet bulanan (satu bulan tertentu) atau omzet akumulatif (total sampai bulan tertentu). Gunakan logika yang sesuai dan tunjukkan perhitungannya secara singkat.
- Jangan menolak menjawab hanya karena data terbatas — justru bantu klien dengan estimasi terbaik yang bisa dibuat dari data yang ada.
- Bedakan mana jawaban berbasis data riil vs estimasi/asumsi dengan menyebutnya secara eksplisit.
- Jangan mengarang angka yang tidak bisa diturunkan dari data.
- JANGAN gunakan markdown: tidak ada **bold**, *italic*, bullet -, atau heading #. Tulis plain text biasa saja.

=== DATA KEUANGAN KLIEN TAHUN {$c['year']} ===
Bulan dengan data : {$c['activeMonthNames']} ({$c['monthsPassed']} bulan — rekening baru dibuka, bukan data sejak Januari)
Total Pemasukan   : {$fmt($c['totalIn'])}
Total Pengeluaran : {$fmt($c['totalOut'])}
Biaya Variabel    : {$fmt($c['variableCosts'])}
Laba Bersih       : {$fmt($c['netProfit'])}
Batas PPh Final   : {$fmt($c['limit'])}
Rezim Pajak       : {$regime}
Estimasi Pajak    : {$fmt($c['overLimit'] ? $c['pphBadan'] : $c['pphFinal'])}

Klasifikasi Pengeluaran:
{$catLines}
=== AKHIR DATA ===
PROMPT;
    }
}
