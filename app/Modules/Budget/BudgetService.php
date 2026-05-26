<?php

namespace App\Modules\Budget;

use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetPeriod;
use App\Models\ExpenseTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BudgetService
{
    // ── Categories ────────────────────────────────────────────

    public function getCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return BudgetCategory::with(['items' => fn($q) => $q->where('is_active', true)->orderBy('name')])->get();
    }

    public function createCategory(array $data): BudgetCategory
    {
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

    public function getTransactions(?string $month = null, ?int $budgetItemId = null)
    {
        return ExpenseTransaction::with('budgetItem.category')
            ->when($month, fn($q) => $q->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$month]))
            ->when($budgetItemId, fn($q) => $q->where('budget_item_id', $budgetItemId))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
    }

    public function createTransaction(array $data): ExpenseTransaction
    {
        $tx = ExpenseTransaction::create($data);
        $this->syncPeriod($tx->budgetItem->category_id, substr($data['transaction_date'], 0, 7));
        return $tx->load('budgetItem.category');
    }

    public function updateTransaction(int $id, array $data): ExpenseTransaction
    {
        $tx = ExpenseTransaction::findOrFail($id);
        $oldMonth = $tx->transaction_date->format('Y-m');
        $tx->update($data);
        $tx->refresh();
        $newMonth = $tx->transaction_date->format('Y-m');
        $this->syncPeriod($tx->budgetItem->category_id, $oldMonth);
        if ($newMonth !== $oldMonth) {
            $this->syncPeriod($tx->budgetItem->category_id, $newMonth);
        }
        return $tx->load('budgetItem.category');
    }

    public function deleteTransaction(int $id): void
    {
        $tx = ExpenseTransaction::findOrFail($id);
        $month = $tx->transaction_date->format('Y-m');
        $catId = $tx->budgetItem->category_id;
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
        return $tx->fresh('budgetItem.category');
    }

    // ── Summary / Dashboard ───────────────────────────────────

    public function getSummary(string $month): array
    {
        $categories = BudgetCategory::with(['items' => fn($q) => $q->where('is_active', true)])->get();

        $result = [];
        $totalPlanned = 0;
        $totalActual  = 0;

        foreach ($categories as $cat) {
            $planned = $cat->items->sum('total_monthly_budget');

            $actual = ExpenseTransaction::whereHas('budgetItem', fn($q) => $q->where('category_id', $cat->id))
                ->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$month])
                ->sum('amount');

            $pct    = $planned > 0 ? round(($actual / $planned) * 100, 1) : 0;
            $status = $pct > 100 ? 'over_budget' : ($pct >= 80 ? 'warning' : 'on_track');

            // Item yang belum/kurang bayar bulan ini
            $unpaid = [];
            foreach ($cat->items as $item) {
                $paid = ExpenseTransaction::where('budget_item_id', $item->id)
                    ->whereRaw("DATE_FORMAT(transaction_date, '%Y-%m') = ?", [$month])
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
                'category'   => $cat->name,
                'category_id'=> $cat->id,
                'planned'    => $planned,
                'actual'     => $actual,
                'pct'        => $pct,
                'status'     => $status,
                'unpaid'     => $unpaid,
            ];

            $totalPlanned += $planned;
            $totalActual  += $actual;
        }

        return [
            'month'         => $month,
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
