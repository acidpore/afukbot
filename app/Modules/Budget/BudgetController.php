<?php

namespace App\Modules\Budget;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BudgetController extends Controller
{
    public function __construct(private BudgetService $service) {}

    // ── Categories ─────────────────────────────────────────────

    public function indexCategories()
    {
        return response()->json($this->service->getCategories());
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        return response()->json($this->service->createCategory($data), 201);
    }

    public function updateCategory(Request $request, int $id)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        return response()->json($this->service->updateCategory($id, $data));
    }

    public function destroyCategory(int $id)
    {
        $this->service->deleteCategory($id);
        return response()->json(null, 204);
    }

    // ── Items ──────────────────────────────────────────────────

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'name'        => 'required|string|max:150',
            'unit_cost'   => 'required|numeric|min:0',
            'rate'        => 'required|in:harian,mingguan,dua_mingguan,bulanan,custom',
            'multiplier'  => 'required|integer|min:1',
            'is_active'   => 'boolean',
        ]);
        return response()->json($this->service->createItem($data), 201);
    }

    public function updateItem(Request $request, int $id)
    {
        $data = $request->validate([
            'category_id' => 'sometimes|exists:budget_categories,id',
            'name'        => 'sometimes|string|max:150',
            'unit_cost'   => 'sometimes|numeric|min:0',
            'rate'        => 'sometimes|in:harian,mingguan,dua_mingguan,bulanan,custom',
            'multiplier'  => 'sometimes|integer|min:1',
            'is_active'   => 'boolean',
        ]);
        return response()->json($this->service->updateItem($id, $data));
    }

    public function bulkStoreItems(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:budget_categories,id',
            'items'       => 'required|array|min:1',
            'items.*.name'       => 'required|string|max:150',
            'items.*.unit_cost'  => 'required|numeric|min:0',
            'items.*.rate'       => 'required|in:harian,mingguan,dua_mingguan,bulanan,custom',
            'items.*.multiplier' => 'required|integer|min:1',
        ]);

        $created = [];
        foreach ($request->items as $item) {
            $created[] = $this->service->createItem(array_merge($item, [
                'category_id' => $request->category_id,
                'is_active'   => true,
            ]));
        }

        return response()->json($created, 201);
    }

    public function destroyItem(int $id)
    {
        $this->service->deleteItem($id);
        return response()->json(null, 204);
    }

    // ── Transactions ───────────────────────────────────────────

    public function indexTransactions(Request $request)
    {
        $data = $this->service->getTransactions(
            $request->query('month'),
            $request->query('budget_item_id') ? (int) $request->query('budget_item_id') : null,
            $request->query('date')
        );
        return response()->json($data);
    }

    public function storeTransaction(Request $request)
    {
        $data = $request->validate([
            'budget_item_id'   => 'required|exists:budget_items,id',
            'amount'           => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'note'             => 'nullable|string|max:500',
        ]);
        return response()->json($this->service->createTransaction($data), 201);
    }

    public function updateTransaction(Request $request, int $id)
    {
        $data = $request->validate([
            'budget_item_id'   => 'sometimes|exists:budget_items,id',
            'amount'           => 'sometimes|numeric|min:0',
            'transaction_date' => 'sometimes|date',
            'note'             => 'nullable|string|max:500',
        ]);
        return response()->json($this->service->updateTransaction($id, $data));
    }

    public function destroyTransaction(int $id)
    {
        $this->service->deleteTransaction($id);
        return response()->json(null, 204);
    }

    public function uploadReceipt(Request $request, int $id)
    {
        $request->validate(['receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        return response()->json($this->service->uploadReceipt($id, $request->file('receipt')));
    }

    // ── Summary / Dashboard ────────────────────────────────────

    public function summary(Request $request)
    {
        return response()->json($this->service->getSummary());
    }

    public function getPeriodSetting()
    {
        return response()->json($this->service->getPeriodSetting());
    }

    public function setPeriodSetting(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);
        return response()->json($this->service->setPeriodSetting($data['start_date'], $data['end_date']));
    }

    public function trend(Request $request)
    {
        $months = (int) $request->query('months', 6);
        return response()->json($this->service->getTrend($months));
    }
}
