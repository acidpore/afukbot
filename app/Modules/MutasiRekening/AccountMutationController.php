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

        $yearlyTotalIn       = $yearlyRows->where('type', 'in')->sum('amount');
        $yearlyTotalOut      = $yearlyRows->where('type', 'out')->sum('amount');
        $yearlyVariableCosts = $yearlyRows->where('type', 'in')->sum(fn($m) =>
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

        $totalIn        = $yearly->where('type', 'in')->sum('amount');
        $totalOut       = $yearly->where('type', 'out')->sum('amount');
        $variableCosts  = $yearly->where('type', 'in')->sum(fn($m) => collect($m->costs ?? [])->sum('amount'));

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

        return response()->json([
            'year'                    => $year,
            'total_in'                => $totalIn,
            'total_out'               => $totalOut,
            'variable_costs'          => $variableCosts,
            'variable_cost_breakdown' => $variableCostBreakdown,
            'net_profit'              => $netProfit,
            'pph_final'               => $pphFinal,
            'pph_badan'               => $pphBadan,
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
        ]);

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
        ]);

        $mutation = AccountMutation::findOrFail($id);
        $mutation->update($data);
        return response()->json($mutation);
    }

    public function destroy(int $id)
    {
        AccountMutation::findOrFail($id)->delete();
        return response()->json(['message' => 'Transaksi dihapus.']);
    }
}
