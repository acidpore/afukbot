<?php

namespace App\Modules\SuratJalan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SuratJalanController extends Controller
{
    protected SuratJalanService $service;

    public function __construct(SuratJalanService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return $this->sendResponse($this->service->getAll(), 'Surat jalan retrieved');
    }

    public function invoicesWithProgress()
    {
        return $this->sendResponse($this->service->getInvoicesWithProgress(), 'Invoices with progress retrieved');
    }

    public function completedInvoices()
    {
        return $this->sendResponse($this->service->getCompletedInvoices(), 'Completed invoices retrieved');
    }

    public function bySale($saleId)
    {
        return $this->sendResponse($this->service->getBySaleId((int) $saleId), 'Surat jalan by sale retrieved');
    }

    public function store(Request $request)
    {
        $request->validate([
            'sale_id'               => 'required|integer|exists:sales,id',
            'tanggal_kirim'         => 'required|date',
            'catatan'               => 'nullable|string',
            'items'                 => 'required|array|min:1',
            'items.*.sale_item_id'  => 'required|integer|exists:sale_items,id',
            'items.*.qty_kirim'     => 'required|integer|min:1',
        ]);

        try {
            $sj = $this->service->create($request->all());
            return $this->sendResponse($sj, 'Surat jalan berhasil dibuat', 201);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $this->service->delete((int) $id);
            return $this->sendResponse(null, 'Surat jalan berhasil dihapus');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }
}
