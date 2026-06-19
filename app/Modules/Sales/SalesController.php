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
            'shipped_at'                      => 'nullable|date',
            'notes'                           => 'nullable|string',
            'paid_amount'                     => 'nullable|integer|min:0',
            'items'                           => 'required|array|min:1',
            'items.*.item_name'               => 'required|string',
            'items.*.description'             => 'nullable|string',
            'items.*.qty'                     => 'required|integer|min:1',
            'items.*.unit_price'              => 'required|integer|min:0',
            'items.*.inventory_item_ids'      => 'nullable|array',
            'items.*.inventory_item_ids.*'    => 'integer',
            'items.*.is_new_item'             => 'nullable|boolean',
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
            'shipped_at'                   => 'nullable|date',
            'notes'                        => 'nullable|string',
            'sender_name'                  => 'nullable|string',
            'sender_address'               => 'nullable|string',
            'bank_account_name'            => 'nullable|string',
            'bank_name'                    => 'nullable|string',
            'bank_account_number'          => 'nullable|string',
            'paid_amount'                  => 'nullable|integer|min:0',
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

    public function ship(Request $request, $id)
    {
        $request->validate([
            'shipped_at' => 'nullable|date',
            'notes'      => 'nullable|string',
        ]);
        try {
            $sale = $this->salesService->markAsShipped(
                (int) $id,
                $request->input('shipped_at'),
                $request->input('notes')
            );
            return $this->sendResponse($sale, 'Invoice ditandai sebagai terkirim');
        } catch (\Exception $e) {
            $decoded = json_decode($e->getMessage(), true);
            if (isset($decoded['stock_errors'])) {
                return $this->sendError('Stok tidak cukup untuk beberapa item.', $decoded, 422);
            }
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function setDipesan($id)
    {
        $sale = \App\Models\Sale::findOrFail((int) $id);
        if ($sale->status !== 'rencana') return $this->sendError('Hanya invoice rencana yang bisa dipesan.', [], 422);
        $sale->update(['status' => 'dipesan']);
        return $this->sendResponse($sale->fresh(), 'Invoice ditandai sebagai dipesan.');
    }

    public function cancelDipesan($id)
    {
        $sale = \App\Models\Sale::findOrFail((int) $id);
        if ($sale->status !== 'dipesan') return $this->sendError('Invoice bukan berstatus dipesan.', [], 422);
        $sale->update(['status' => 'rencana']);
        return $this->sendResponse($sale->fresh(), 'Status invoice dikembalikan ke rencana.');
    }

    public function revertStock($id)
    {
        try {
            $sale = $this->salesService->revertStock((int) $id);
            return $this->sendResponse($sale, 'Stok berhasil dikembalikan, status invoice kembali ke belum dikirim');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }
    }

    public function uploadAttachment(Request $request, $id)
    {
        $request->validate(['attachment' => 'required|file|mimes:pdf|max:10240']);
        $sale = $this->salesService->uploadAttachment((int) $id, $request->file('attachment'));
        return $this->sendResponse($sale, 'Lampiran berhasil diunggah');
    }

    public function deleteAttachment($id)
    {
        $sale = $this->salesService->deleteAttachment((int) $id);
        return $this->sendResponse($sale, 'Lampiran berhasil dihapus');
    }

    public function pendingItems()
    {
        return $this->sendResponse($this->salesService->getPendingItems(), 'Pending items retrieved');
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
