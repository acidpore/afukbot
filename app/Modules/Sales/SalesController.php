<?php

namespace App\Modules\Sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    protected SalesService $salesService;

    public function __construct(SalesService $salesService)
    {
        $this->salesService = $salesService;
    }

    public function index()
    {
        return $this->sendResponse($this->salesService->getAll(), 'Sales retrieved successfully');
    }

    public function show($id)
    {
        return $this->sendResponse($this->salesService->getById((int) $id), 'Sale retrieved successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'recipient_name'                  => 'required|string',
            'recipient_address'               => 'nullable|string',
            'invoice_date'                    => 'required|date',
            'notes'                           => 'nullable|string',
            'items'                           => 'required|array|min:1',
            'items.*.item_name'               => 'required|string',
            'items.*.description'             => 'nullable|string',
            'items.*.qty'                     => 'required|integer|min:1',
            'items.*.unit_price'              => 'required|integer|min:0',
            'items.*.inventory_item_ids'      => 'nullable|array',
            'items.*.inventory_item_ids.*'    => 'integer',
        ]);

        $sale = $this->salesService->create($request->all());
        return $this->sendResponse($sale, 'Invoice created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'recipient_name'               => 'required|string',
            'recipient_address'            => 'nullable|string',
            'invoice_date'                 => 'required|date',
            'notes'                        => 'nullable|string',
            'items'                        => 'sometimes|array|min:1',
            'items.*.item_name'            => 'required_with:items|string',
            'items.*.description'          => 'nullable|string',
            'items.*.qty'                  => 'required_with:items|integer|min:1',
            'items.*.unit_price'           => 'required_with:items|integer|min:0',
            'items.*.inventory_item_ids'   => 'nullable|array',
        ]);

        $sale = $this->salesService->update((int) $id, $request->all());
        return $this->sendResponse($sale, 'Invoice updated successfully');
    }

    public function setPayment(Request $request, $id)
    {
        $request->validate(['amount' => 'required|integer|min:0']);
        try {
            $sale = $this->salesService->setPayment((int) $id, (int) $request->amount);
            return $this->sendResponse($sale, 'Pembayaran berhasil dikoreksi');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function pay(Request $request, $id)
    {
        $request->validate(['amount' => 'required|integer|min:1']);
        try {
            $sale = $this->salesService->recordPayment((int) $id, (int) $request->amount);
            return $this->sendResponse($sale, 'Pembayaran berhasil dicatat');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function ship($id)
    {
        try {
            $sale = $this->salesService->markAsShipped((int) $id);
            return $this->sendResponse($sale, 'Invoice ditandai sebagai terkirim');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function destroy($id)
    {
        try {
            $this->salesService->delete((int) $id);
            return $this->sendResponse(null, 'Sale deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }
}
