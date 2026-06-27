<?php

namespace App\Modules\Budget;

use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetPeriod;
use App\Models\BudgetProposal;
use App\Models\Expense;
use App\Models\ExpenseTransaction;
use App\Models\RabPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BudgetService
{
    // ── Periods (RAB per periode) ─────────────────────────────

    public function listPeriods(): \Illuminate\Database\Eloquent\Collection
    {
        return RabPeriod::orderByDesc('start_date')->orderByDesc('id')->get();
    }

    private function activePeriod(): RabPeriod
    {
        return RabPeriod::where('is_active', true)->first()
            ?? RabPeriod::create([
                'name'       => 'Periode ' . now()->format('M Y'),
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date'   => now()->endOfMonth()->toDateString(),
                'is_active'  => true,
            ]);
    }

    private function resolvePeriod(?int $periodId): RabPeriod
    {
        return $periodId ? RabPeriod::findOrFail($periodId) : $this->activePeriod();
    }

    /** Buat RAB periode baru — clone kategori + item dari periode aktif, lalu jadikan aktif. */
    public function createPeriod(array $data): RabPeriod
    {
        return DB::transaction(function () use ($data) {
            $source = $this->activePeriod();
            RabPeriod::query()->update(['is_active' => false]);

            $new = RabPeriod::create([
                'name'       => $data['name'],
                'start_date' => $data['start_date'],
                'end_date'   => $data['end_date'],
                'is_active'  => true,
            ]);

            foreach (BudgetCategory::with('items')->where('period_id', $source->id)->get() as $cat) {
                $newCat = BudgetCategory::create([
                    'name'         => $cat->name,
                    'total_budget' => $cat->total_budget,
                    'period_id'    => $new->id,
                ]);
                foreach ($cat->items as $item) {
                    BudgetItem::create([
                        'category_id' => $newCat->id,
                        'name'        => $item->name,
                        'unit_cost'   => $item->unit_cost,
                        'rate'        => $item->rate,
                        'multiplier'  => $item->multiplier,
                        'is_active'   => $item->is_active,
                    ]); // total_monthly_budget kolom generated, jangan di-set
                }
            }

            return $new;
        });
    }

    public function deletePeriod(int $id): void
    {
        if (RabPeriod::count() <= 1) {
            abort(422, 'Tidak bisa menghapus satu-satunya periode.');
        }

        DB::transaction(function () use ($id) {
            $period = RabPeriod::findOrFail($id);

            // Bersihkan mirror Expense sebelum cascade FK menghapus transaksi
            $txIds = ExpenseTransaction::whereHas('budgetItem.category', fn($q) => $q->where('period_id', $id))->pluck('id');
            if ($txIds->isNotEmpty()) {
                Expense::whereIn('expense_transaction_id', $txIds)->delete();
            }

            // Hapus kategori → cascade ke items → cascade ke expense_transactions
            BudgetCategory::where('period_id', $id)->get()->each->delete();

            $wasActive = $period->is_active;
            $period->delete();

            if ($wasActive) {
                RabPeriod::orderByDesc('start_date')->orderByDesc('id')->first()
                    ?->update(['is_active' => true]);
            }
        });
    }

    // ── Proposals (Pengajuan) ─────────────────────────────────

    public function listProposals(?int $periodId = null): \Illuminate\Database\Eloquent\Collection
    {
        $period = $this->resolvePeriod($periodId);
        return BudgetProposal::where('period_id', $period->id)
            ->orderByRaw("status = 'bought'") // pending dulu, terbeli di bawah
            ->orderByDesc('id')
            ->get();
    }

    public function createProposal(array $data): BudgetProposal
    {
        $data['period_id'] = $this->activePeriod()->id;
        return BudgetProposal::create($data);
    }

    public function updateProposal(int $id, array $data): BudgetProposal
    {
        $proposal = BudgetProposal::findOrFail($id);
        if (($data['status'] ?? null) === 'bought') {
            $data['bought_at'] = $proposal->bought_at?->toDateString() ?? now()->toDateString();
        } elseif (($data['status'] ?? null) === 'pending') {
            $data['bought_at'] = null;
        }
        $proposal->update($data);
        return $proposal->fresh();
    }

    public function deleteProposal(int $id): void
    {
        BudgetProposal::findOrFail($id)->delete();
    }

    // ── Categories ────────────────────────────────────────────

    public function getCategories(?int $periodId = null): \Illuminate\Database\Eloquent\Collection
    {
        $period = $this->resolvePeriod($periodId);
        return BudgetCategory::where('period_id', $period->id)
            ->with(['items' => fn($q) => $q->where('is_active', true)->orderBy('name')])
            ->get();
    }

    public function createCategory(array $data): BudgetCategory
    {
        $data['period_id'] = $this->activePeriod()->id;
        return BudgetCategory::create($data);
    }

    public function updateCategory(int $id, array $data): BudgetCategory
    {
        $cat = BudgetCategory::findOrFail($id);
        $cat->update($data);
        return $cat->fresh();
    }

    public function deleteCategory(int $id): void
    {
        BudgetCategory::findOrFail($id)->delete();
    }

    // ── Items ─────────────────────────────────────────────────

    public function createItem(array $data): BudgetItem
    {
        return BudgetItem::create($data)->load('category');
    }

    public function updateItem(int $id, array $data): BudgetItem
    {
        $item = BudgetItem::findOrFail($id);
        $item->update($data);
        return $item->fresh('category');
    }

    public function deleteItem(int $id): void
    {
        BudgetItem::findOrFail($id)->delete();
    }

    // ── Transactions ──────────────────────────────────────────

    public function getPeriodSetting(?int $periodId = null): array
    {
        $period = $this->resolvePeriod($periodId);
        return [
            'id'         => $period->id,
            'name'       => $period->name,
            'start_date' => $period->start_date->toDateString(),
            'end_date'   => $period->end_date->toDateString(),
        ];
    }

    public function setPeriodSetting(string $startDate, string $endDate): array
    {
        $period = $this->activePeriod();
        $period->update(['start_date' => $startDate, 'end_date' => $endDate]);
        return $this->getPeriodSetting($period->id);
    }

    public function getTransactions(?string $month = null, ?int $budgetItemId = null, ?string $date = null, ?int $periodId = null)
    {
        $period = $this->resolvePeriod($periodId);

        return ExpenseTransaction::with('budgetItem.category')
            ->whereHas('budgetItem.category', fn($q) => $q->where('period_id', $period->id))
            ->when($date, fn($q) => $q->whereDate('transaction_date', $date))
            ->when(!$date && $month, fn($q) => $q->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$month]))
            ->when(!$date && !$month, fn($q) => $q->whereBetween('transaction_date', [$period->start_date->toDateString(), $period->end_date->toDateString()]))
            ->when($budgetItemId, fn($q) => $q->where('budget_item_id', $budgetItemId))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
    }

    public function createTransaction(array $data): ExpenseTransaction
    {
        $tx = ExpenseTransaction::create($data);
        $tx->load('budgetItem.category');
        $this->syncPeriod($tx->budgetItem->category_id, substr($data['transaction_date'], 0, 7));
        $this->upsertExpenseMirror($tx);
        return $tx;
    }

    public function updateTransaction(int $id, array $data): ExpenseTransaction
    {
        $tx = ExpenseTransaction::findOrFail($id);
        $oldMonth = $tx->transaction_date->format('Y-m');
        $tx->update($data);
        $tx->refresh()->load('budgetItem.category');
        $newMonth = $tx->transaction_date->format('Y-m');
        $this->syncPeriod($tx->budgetItem->category_id, $oldMonth);
        if ($newMonth !== $oldMonth) {
            $this->syncPeriod($tx->budgetItem->category_id, $newMonth);
        }
        $this->upsertExpenseMirror($tx);
        return $tx;
    }

    public function deleteTransaction(int $id): void
    {
        $tx = ExpenseTransaction::findOrFail($id);
        $month = $tx->transaction_date->format('Y-m');
        $catId = $tx->budgetItem->category_id;

        Expense::where('expense_transaction_id', $tx->id)->delete();

        $tx->delete();
        $this->syncPeriod($catId, $month);
    }

    public function uploadReceipt(int $id, \Illuminate\Http\UploadedFile $file): ExpenseTransaction
    {
        $tx = ExpenseTransaction::findOrFail($id);
        if ($tx->receipt_path) {
            Storage::disk('public')->delete($tx->receipt_path);
        }
        $path = $file->store('budget-receipts', 'public');
        $tx->update(['receipt_path' => $path]);
        $tx->refresh()->load('budgetItem.category');
        $this->upsertExpenseMirror($tx);
        return $tx;
    }

    private function upsertExpenseMirror(ExpenseTransaction $tx): void
    {
        $categoryName = $tx->budgetItem->category->name ?? 'RAB';
        $itemName     = $tx->budgetItem->name ?? '';

        Expense::updateOrCreate(
            ['expense_transaction_id' => $tx->id],
            [
                'expense_date' => $tx->transaction_date->toDateString(),
                'category'     => $categoryName,
                'description'  => $itemName,
                'amount'       => (int) $tx->amount,
                'paid_by'      => null,
                'notes'        => $tx->note,
                'receipt_path' => $tx->receipt_path,
            ]
        );
    }

    // ── Summary / Dashboard ───────────────────────────────────

    public function getSummary(?int $periodId = null): array
    {
        $period     = $this->resolvePeriod($periodId);
        $startDate  = $period->start_date->toDateString();
        $endDate    = $period->end_date->toDateString();

        $categories = BudgetCategory::where('period_id', $period->id)
            ->with(['items' => fn($q) => $q->where('is_active', true)])->get();

        $result = [];
        $totalPlanned = 0;
        $totalActual  = 0;

        foreach ($categories as $cat) {
            $planned = $cat->items->sum('total_monthly_budget');

            $actual = ExpenseTransaction::whereHas('budgetItem', fn($q) => $q->where('category_id', $cat->id))
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->sum('amount');

            $pct    = $planned > 0 ? round(($actual / $planned) * 100, 1) : 0;
            $status = $pct > 100 ? 'over_budget' : ($pct >= 80 ? 'warning' : 'on_track');

            $unpaid = [];
            foreach ($cat->items as $item) {
                $paid = ExpenseTransaction::where('budget_item_id', $item->id)
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->sum('amount');
                $needed = $item->total_monthly_budget;
                if ($paid < $needed) {
                    $unpaid[] = [
                        'id'        => $item->id,
                        'name'      => $item->name,
                        'needed'    => $needed,
                        'paid'      => $paid,
                        'remaining' => $needed - $paid,
                    ];
                }
            }

            $result[] = [
                'category'    => $cat->name,
                'category_id' => $cat->id,
                'planned'     => $planned,
                'actual'      => $actual,
                'pct'         => $pct,
                'status'      => $status,
                'unpaid'      => $unpaid,
            ];

            $totalPlanned += $planned;
            $totalActual  += $actual;
        }

        return [
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'total_planned' => $totalPlanned,
            'total_actual'  => $totalActual,
            'total_sisa'    => $totalPlanned - $totalActual,
            'total_pct'     => $totalPlanned > 0 ? round(($totalActual / $totalPlanned) * 100, 1) : 0,
            'categories'    => $result,
        ];
    }

    public function getTrend(int $months = 3): array
    {
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $actual = ExpenseTransaction::whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$month])->sum('amount');
            $result[] = ['month' => $month, 'actual' => $actual];
        }
        return $result;
    }

    // ── Internal ──────────────────────────────────────────────

    private function syncPeriod(int $catId, string $month): void
    {
        $planned = BudgetItem::where('category_id', $catId)->where('is_active', true)->sum('total_monthly_budget');
        $actual  = ExpenseTransaction::whereHas('budgetItem', fn($q) => $q->where('category_id', $catId))
            ->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$month])
            ->sum('amount');
        $pct     = $planned > 0 ? ($actual / $planned) * 100 : 0;
        $status  = $pct > 100 ? 'over_budget' : ($pct >= 80 ? 'warning' : 'on_track');

        BudgetPeriod::updateOrCreate(
            ['month' => $month, 'category_id' => $catId],
            ['planned_amount' => $planned, 'actual_amount' => $actual, 'status' => $status]
        );
    }
}
