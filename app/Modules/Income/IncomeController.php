<?php

namespace App\Modules\Income;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    protected IncomeService $incomeService;

    public function __construct(IncomeService $incomeService)
    {
        $this->incomeService = $incomeService;
    }

    public function index()
    {
        return $this->sendResponse($this->incomeService->getAll(), 'Incomes retrieved successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'income_date' => 'required|date',
            'source'      => 'required|string|max:100',
            'description' => 'required|string|max:255',
            'amount'      => 'required|integer|min:1',
            'notes'       => 'nullable|string',
        ]);

        $income = $this->incomeService->create($request->all());
        return $this->sendResponse($income, 'Pemasukan berhasil dicatat', 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'income_date' => 'required|date',
            'source'      => 'required|string|max:100',
            'description' => 'required|string|max:255',
            'amount'      => 'required|integer|min:1',
            'notes'       => 'nullable|string',
        ]);

        $income = $this->incomeService->update((int) $id, $request->all());
        return $this->sendResponse($income, 'Pemasukan berhasil diperbarui');
    }

    public function destroy($id)
    {
        try {
            $this->incomeService->delete((int) $id);
            return $this->sendResponse(null, 'Pemasukan berhasil dihapus');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        try {
            $result = $this->incomeService->importCsv($request->file('file'));
            return $this->sendResponse($result, "{$result['imported']} data berhasil diimport");
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }
}
