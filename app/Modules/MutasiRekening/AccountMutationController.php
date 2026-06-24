<?php

namespace App\Modules\MutasiRekening;

use App\Models\AccountMutation;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AccountMutationController extends Controller
{
    public function index(Request $request)
    {
        $bankAccountId = $request->integer('bank_account_id');
        $month         = $request->integer('month', now()->month);
        $year          = $request->integer('year', now()->year);

        $opening = AccountMutation::where('bank_account_id', $bankAccountId)
            ->where('type', 'opening')
            ->value('amount') ?? 0;

        // Saldo awal periode = opening + semua mutasi sebelum bulan ini
        $balanceBefore = AccountMutation::where('bank_account_id', $bankAccountId)
            ->whereNotIn('type', ['opening'])
            ->where('date', '<', "{$year}-{$month}-01")
            ->get()
            ->reduce(fn($carry, $m) => $carry + ($m->type === 'in' ? $m->amount : -$m->amount), (int) $opening);

        $mutations = AccountMutation::where('bank_account_id', $bankAccountId)
            ->whereNotIn('type', ['opening'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        // Hitung saldo berjalan
        $running = $balanceBefore;
        $rows    = $mutations->map(function ($m) use (&$running) {
            $running += $m->type === 'in' ? $m->amount : -$m->amount;
            return array_merge($m->toArray(), ['balance_after' => $running]);
        });

        // Summary bulan ini
        $totalIn  = $mutations->where('type', 'in')->sum('amount');
        $totalOut = $mutations->where('type', 'out')->sum('amount');

        // Saldo akhir keseluruhan
        $finalBalance = AccountMutation::where('bank_account_id', $bankAccountId)
            ->whereNotIn('type', ['opening'])
            ->get()
            ->reduce(fn($carry, $m) => $carry + ($m->type === 'in' ? $m->amount : -$m->amount), (int) $opening);

        $yearlyRows = AccountMutation::where('bank_account_id', $bankAccountId)
            ->whereNotIn('type', ['opening'])
            ->whereYear('date', $year)
            ->get();

        $yearlyTotalIn       = $yearlyRows->where('type', 'in')->where('is_omzet', true)->sum('amount');
        $yearlyTotalOut      = $yearlyRows->where('type', 'out')->sum('amount');
        $yearlyVariableCosts = $yearlyRows->where('type', 'in')->where('is_omzet', true)->sum(fn($m) =>
            collect($m->costs ?? [])->sum('amount')
        );

        return response()->json([
            'opening_balance'      => $opening,
            'balance_before'       => $balanceBefore,
            'final_balance'        => $finalBalance,
            'total_in'             => $totalIn,
            'total_out'            => $totalOut,
            'yearly_total_in'      => $yearlyTotalIn,
            'yearly_total_out'     => $yearlyTotalOut,
            'yearly_variable_costs'=> $yearlyVariableCosts,
            'mutations'            => $rows->values(),
        ]);
    }

    public function setOpening(Request $request)
    {
        $data = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'amount'          => 'required|integer|min:0',
            'date'            => 'required|date',
        ]);

        AccountMutation::updateOrCreate(
            ['bank_account_id' => $data['bank_account_id'], 'type' => 'opening'],
            ['amount' => $data['amount'], 'date' => $data['date'], 'description' => 'Saldo Awal']
        );

        return response()->json(['message' => 'Saldo awal disimpan.']);
    }

    public function taxSummary(Request $request)
    {
        $bankAccountId = $request->integer('bank_account_id');
        $year          = $request->integer('year', now()->year);

        $opening = AccountMutation::where('bank_account_id', $bankAccountId)
            ->where('type', 'opening')->value('amount') ?? 0;

        $yearly = AccountMutation::where('bank_account_id', $bankAccountId)
            ->whereNotIn('type', ['opening'])
            ->whereYear('date', $year)
            ->get();

        $totalIn        = $yearly->where('type', 'in')->where('is_omzet', true)->sum('amount');
        $totalOut       = $yearly->where('type', 'out')->sum('amount');
        $variableCosts  = $yearly->where('type', 'in')->where('is_omzet', true)->sum(fn($m) => collect($m->costs ?? [])->sum('amount'));

        // Klasifikasi pengeluaran berdasarkan kategori
        $deductibleKeywords    = ['gaji', 'sewa', 'listrik', 'air', 'internet', 'telepon', 'operasional', 'supplier',
                                   'bahan', 'atk', 'transport', 'bensin', 'bbm', 'marketing', 'iklan', 'pembelian',
                                   'servis', 'perbaikan', 'cetak', 'pengiriman', 'kirim', 'kuli', 'jasa'];
        $nonDeductibleKeywords = ['tarik tunai', 'pribadi', 'prive', 'personal', 'transfer pribadi', 'tunai'];

        $expenseByCategory = $yearly->where('type', 'out')
            ->groupBy('category')
            ->map(fn($rows, $cat) => [
                'category' => $cat ?: 'Tanpa Kategori',
                'total'    => $rows->sum('amount'),
                'count'    => $rows->count(),
                'status'   => (function() use ($cat, $deductibleKeywords, $nonDeductibleKeywords) {
                    $lower = strtolower($cat ?? '');
                    foreach ($nonDeductibleKeywords as $kw) { if (str_contains($lower, $kw)) return 'non'; }
                    foreach ($deductibleKeywords    as $kw) { if (str_contains($lower, $kw)) return 'deductible'; }
                    return 'review';
                })(),
            ])
            ->values();

        // Biaya variabel per label (dari semua transaksi masuk)
        $variableCostBreakdown = $yearly->where('type', 'in')
            ->flatMap(fn($m) => collect($m->costs ?? []))
            ->groupBy('label')
            ->map(fn($rows, $label) => ['label' => $label, 'total' => $rows->sum('amount')])
            ->values();

        $deductibleTotal    = $expenseByCategory->where('status', 'deductible')->sum('total');
        $nonDeductibleTotal = $expenseByCategory->where('status', 'non')->sum('total');
        $reviewTotal        = $expenseByCategory->where('status', 'review')->sum('total');

        $netProfit          = $totalIn - $totalOut - $variableCosts;
        $pphFinal           = round($totalIn * 0.005);
        $pphBadan           = $netProfit > 0 ? round($netProfit * 0.22) : 0;
        $limitPPh           = 4_800_000_000;
        $monthlyBreakdown = $yearly->whereNotIn('type', ['opening'])
            ->groupBy(fn($m) => (int) date('n', strtotime($m->date)))
            ->map(fn($rows, $mon) => [
                'month'      => $mon,
                'total_in'   => $rows->where('type', 'in')->sum('amount'),
                'total_out'  => $rows->where('type', 'out')->sum('amount'),
                'cumulative' => 0, // filled below
            ])
            ->sortKeys()
            ->values();

        $cumulative = 0;
        $monthlyBreakdown = $monthlyBreakdown->map(function ($row) use (&$cumulative) {
            $cumulative += $row['total_in'];
            $row['cumulative'] = $cumulative;
            return $row;
        });

        $activeMonths       = $yearly->whereNotIn('type', ['opening'])
            ->pluck('date')
            ->map(fn($d) => date('n', strtotime($d)))
            ->unique()
            ->count();
        $monthsPassed       = max(1, $activeMonths);
        $angsuranPph25      = $pphBadan > 0 ? round($pphBadan / 12) : 0;
        $avgMonthlyIn       = $monthsPassed > 0 ? round($totalIn / $monthsPassed) : 0;
        $projectedYearlyIn  = round($avgMonthlyIn * 12);
        $monthsToLimit      = $avgMonthlyIn > 0 && $totalIn < $limitPPh
            ? (int) ceil(($limitPPh - $totalIn) / $avgMonthlyIn)
            : null;

        return response()->json([
            'year'                    => $year,
            'total_in'                => $totalIn,
            'total_out'               => $totalOut,
            'variable_costs'          => $variableCosts,
            'variable_cost_breakdown' => $variableCostBreakdown,
            'net_profit'              => $netProfit,
            'pph_final'               => $pphFinal,
            'pph_badan'               => $pphBadan,
            'angsuran_pph25'          => $angsuranPph25,
            'months_passed'           => $monthsPassed,
            'avg_monthly_in'          => $avgMonthlyIn,
            'projected_yearly_in'     => $projectedYearlyIn,
            'months_to_limit'         => $monthsToLimit,
            'monthly_breakdown'       => $monthlyBreakdown,
            'over_limit'              => $totalIn >= $limitPPh,
            'limit'                   => $limitPPh,
            'expense_categories'      => $expenseByCategory->sortByDesc('total')->values(),
            'deductible_total'        => $deductibleTotal,
            'non_deductible_total'    => $nonDeductibleTotal,
            'review_total'            => $reviewTotal,
            'potential_saving'        => $netProfit > 0 && $totalIn >= $limitPPh
                ? round(($deductibleTotal + $variableCosts) * 0.22)
                : 0,
        ]);
    }

    public function reclassifyCategory(Request $request)
    {
        $data = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'year'            => 'required|integer',
            'old_category'    => 'required|string|max:100',
            'new_category'    => 'required|string|max:100',
        ]);

        AccountMutation::where('bank_account_id', $data['bank_account_id'])
            ->whereYear('date', $data['year'])
            ->where('category', $data['old_category'])
            ->update(['category' => $data['new_category']]);

        return response()->json(['message' => 'Kategori diperbarui.']);
    }

    public function categories(Request $request)
    {
        $cats = AccountMutation::where('bank_account_id', $request->integer('bank_account_id'))
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->values();

        return response()->json($cats);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_account_id'  => 'required|exists:bank_accounts,id',
            'date'             => 'required|date',
            'type'             => 'required|in:in,out',
            'amount'           => 'required|integer|min:1',
            'description'      => 'nullable|string|max:255',
            'category'         => 'nullable|string|max:100',
            'costs'            => 'nullable|array',
            'costs.*.label'    => 'required|string|max:100',
            'costs.*.amount'   => 'required|integer|min:0',
            'is_omzet'         => 'boolean',
        ]);

        $data['is_omzet'] = $data['is_omzet'] ?? true;

        return response()->json(AccountMutation::create($data), 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'date'             => 'required|date',
            'type'             => 'required|in:in,out',
            'amount'           => 'required|integer|min:1',
            'description'      => 'nullable|string|max:255',
            'category'         => 'nullable|string|max:100',
            'costs'            => 'nullable|array',
            'costs.*.label'    => 'required|string|max:100',
            'costs.*.amount'   => 'required|integer|min:0',
            'is_omzet'         => 'boolean',
        ]);

        $mutation = AccountMutation::findOrFail($id);
        $mutation->update($data);
        return response()->json($mutation);
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file'            => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
            'bank_account_id' => 'required|exists:bank_accounts,id',
        ]);

        $rows = $this->parseMandiriCsv($request->file('file'));

        return response()->json(['rows' => $rows, 'count' => count($rows)]);
    }

    public function importCommit(Request $request)
    {
        $data = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'rows'            => 'required|array',
            'rows.*.date'     => 'required|date',
            'rows.*.type'     => 'required|in:in,out',
            'rows.*.amount'   => 'required|integer|min:1',
            'rows.*.description' => 'nullable|string|max:255',
        ]);

        $inserted = 0;
        foreach ($data['rows'] as $row) {
            // Skip duplikat (date + amount + type + description sama)
            $exists = AccountMutation::where('bank_account_id', $data['bank_account_id'])
                ->where('date', $row['date'])
                ->where('type', $row['type'])
                ->where('amount', $row['amount'])
                ->where('description', $row['description'] ?? null)
                ->exists();

            if (!$exists) {
                AccountMutation::create([
                    'bank_account_id' => $data['bank_account_id'],
                    'date'            => $row['date'],
                    'type'            => $row['type'],
                    'amount'          => $row['amount'],
                    'description'     => $row['description'] ?? null,
                    'category'        => $row['category'] ?? null,
                ]);
                $inserted++;
            }
        }

        return response()->json(['inserted' => $inserted, 'skipped' => count($data['rows']) - $inserted]);
    }

    private function parseMandiriCsv(\Illuminate\Http\UploadedFile $file): array
    {
        $content  = file_get_contents($file->getRealPath());
        // Mandiri kadang pakai encoding Windows-1252
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines  = preg_split('/\r\n|\r|\n/', trim($content));
        $rows   = [];
        $header = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $cols = str_getcsv($line, ',', '"');
            $cols = array_map('trim', $cols);

            // Cari baris header (mengandung "Tanggal" dan "Nominal" atau "Debet"/"Kredit")
            if ($header === null) {
                $lower = array_map('strtolower', $cols);
                if (in_array('tanggal', $lower) && (in_array('nominal', $lower) || in_array('debet', $lower))) {
                    $header = $lower;
                }
                continue;
            }

            if (count($cols) < count($header)) continue;

            $map = array_combine($header, array_slice($cols, 0, count($header)));

            // Ambil tanggal — format DD/MM/YYYY
            $rawDate = $map['tanggal'] ?? '';
            if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $rawDate, $m)) {
                $date = "{$m[3]}-{$m[2]}-{$m[1]}";
            } else {
                continue; // skip baris tidak valid
            }

            // Deteksi format: kolom "nominal" + "db/cr" ATAU kolom "debet"/"kredit" terpisah
            $type   = null;
            $amount = 0;

            if (isset($map['nominal']) && isset($map['db/cr'])) {
                $dbcr   = strtoupper(trim($map['db/cr']));
                $type   = $dbcr === 'CR' ? 'in' : 'out';
                $amount = (int) preg_replace('/[^0-9]/', '', $map['nominal']);
            } elseif (isset($map['debet']) || isset($map['kredit'])) {
                $debet  = (int) preg_replace('/[^0-9]/', '', $map['debet']  ?? '0');
                $kredit = (int) preg_replace('/[^0-9]/', '', $map['kredit'] ?? '0');
                if ($kredit > 0)      { $type = 'in';  $amount = $kredit; }
                elseif ($debet > 0)   { $type = 'out'; $amount = $debet; }
            }

            if (!$type || $amount <= 0) continue;

            $rows[] = [
                'date'        => $date,
                'type'        => $type,
                'amount'      => $amount,
                'description' => $map['keterangan'] ?? $map['transaksi'] ?? null,
                'category'    => null,
            ];
        }

        return $rows;
    }

    public function destroy(int $id)
    {
        AccountMutation::findOrFail($id)->delete();
        return response()->json(['message' => 'Transaksi dihapus.']);
    }
}
