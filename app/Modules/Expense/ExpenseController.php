<?php

namespace App\Modules\Expense;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    protected ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    public function index()
    {
        return $this->sendResponse($this->expenseService->getAll(), 'Expenses retrieved successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'expense_date' => 'required|date',
            'category'     => 'required|string|max:100',
            'description'  => 'required|string|max:255',
            'amount'       => 'required|integer|min:1',
            'paid_by'      => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
        ]);

        $expense = $this->expenseService->create($request->all());
        return $this->sendResponse($expense, 'Pengeluaran berhasil dicatat', 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'expense_date' => 'required|date',
            'category'     => 'required|string|max:100',
            'description'  => 'required|string|max:255',
            'amount'       => 'required|integer|min:1',
            'paid_by'      => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
        ]);

        $expense = $this->expenseService->update((int) $id, $request->all());
        return $this->sendResponse($expense, 'Pengeluaran berhasil diperbarui');
    }

    public function destroy($id)
    {
        try {
            $this->expenseService->delete((int) $id);
            return $this->sendResponse(null, 'Pengeluaran berhasil dihapus');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function uploadReceipt(Request $request, $id)
    {
        $request->validate(['receipt' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240']);
        $expense = $this->expenseService->uploadReceipt((int) $id, $request->file('receipt'));
        return $this->sendResponse($expense, 'Bukti struk berhasil diunggah');
    }

    public function deleteReceipt($id)
    {
        $expense = $this->expenseService->deleteReceipt((int) $id);
        return $this->sendResponse($expense, 'Bukti struk berhasil dihapus');
    }

    public function summary(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        return $this->sendResponse(
            $this->expenseService->getSummaryByCategory($month),
            'Summary retrieved'
        );
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        try {
            $result = $this->expenseService->importCsv($request->file('file'));
            return $this->sendResponse($result, "{$result['imported']} data berhasil diimport");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }
}
