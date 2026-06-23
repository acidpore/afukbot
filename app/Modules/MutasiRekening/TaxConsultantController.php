<?php

namespace App\Modules\MutasiRekening;

use App\Http\Controllers\Controller;
use App\Models\AccountMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TaxConsultantController extends Controller
{
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent';

    public function chat(Request $request)
    {
        $request->validate([
            'message'         => 'required|string|max:1000',
            'history'         => 'nullable|array|max:20',
            'history.*.role'  => 'required|in:user,model',
            'history.*.text'  => 'required|string',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'year'            => 'required|integer',
        ]);

        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return response()->json(['message' => 'GEMINI_API_KEY belum dikonfigurasi'], 500);
        }

        $context = $this->buildContext($request->integer('bank_account_id'), $request->integer('year'));
        $systemPrompt = $this->buildSystemPrompt($context);

        // Bangun history conversation untuk multi-turn
        $contents = [];

        // Inject system prompt sebagai pesan pertama dari user + ack dari model
        $contents[] = ['role' => 'user',  'parts' => [['text' => $systemPrompt]]];
        $contents[] = ['role' => 'model', 'parts' => [['text' => 'Baik, saya siap membantu sebagai konsultan pajak berdasarkan data keuangan Anda.']]];

        foreach ($request->input('history', []) as $h) {
            $contents[] = ['role' => $h['role'], 'parts' => [['text' => $h['text']]]];
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $request->input('message')]]];

        $response = Http::timeout(30)->post(self::GEMINI_URL . '?key=' . $apiKey, [
            'contents'         => $contents,
            'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 1024],
        ]);

        if (!$response->successful()) {
            \Log::error('Gemini chat failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return response()->json([
                'message' => 'Gagal menghubungi AI. Coba lagi.',
                'debug'   => $response->json(),
            ], 502);
        }

        $text = $response->json('candidates.0.content.parts.0.text', '');

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

        $monthsPassed = ($year === now()->year) ? max(1, now()->month) : 12;

        return compact(
            'year', 'totalIn', 'totalOut', 'variableCosts', 'netProfit',
            'overLimit', 'pphFinal', 'pphBadan', 'limit', 'categories', 'monthsPassed'
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
Kamu adalah konsultan pajak profesional Indonesia yang membantu klien memahami kewajiban pajak mereka.
Gunakan bahasa Indonesia yang santai tapi tetap akurat. Jawab ringkas dan langsung ke inti.
Jangan mengarang data — hanya gunakan data yang diberikan di bawah ini. Jika ditanya sesuatu yang tidak ada datanya, katakan terus terang.

=== DATA KEUANGAN KLIEN TAHUN {$c['year']} ===
Bulan berjalan    : {$c['monthsPassed']} dari 12 bulan
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
