<?php

namespace App\Modules\Sales;

use App\Models\Sale;
use App\Models\Item;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return Sale::with('items')->orderByDesc('invoice_date')->orderByDesc('id')->get();
    }

    public function getById(int $id): Sale
    {
        return Sale::with('items')->findOrFail($id);
    }

    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $invoiceNumber = $this->generateInvoiceNumber();

            $grandTotal = array_sum(array_map(
                fn($item) => $item['qty'] * $item['unit_price'],
                $data['items']
            ));

            $sale = Sale::create([
                'invoice_number'    => $invoiceNumber,
                'recipient_name'    => $data['recipient_name'],
                'recipient_address' => $data['recipient_address'] ?? null,
                'invoice_date'      => $data['invoice_date'],
                'notes'             => $data['notes'] ?? null,
                'grand_total'       => $grandTotal,
                'status'            => 'belum_dikirim',
            ]);

            foreach ($data['items'] as $item) {
                $sale->items()->create([
                    'item_name'           => $item['item_name'],
                    'description'         => $item['description'] ?? null,
                    'qty'                 => $item['qty'],
                    'unit_price'          => $item['unit_price'],
                    'total_price'         => $item['qty'] * $item['unit_price'],
                    'inventory_item_ids'  => $item['inventory_item_ids'] ?? null,
                ]);
            }

            return $sale->load('items');
        });
    }

    public function markAsShipped(int $id): Sale
    {
        return DB::transaction(function () use ($id) {
            $sale = Sale::with('items')->findOrFail($id);

            if ($sale->status === 'sudah_dikirim') {
                throw new \Exception('Invoice sudah berstatus terkirim.');
            }

            foreach ($sale->items as $saleItem) {
                $itemIds = $saleItem->inventory_item_ids ?? [];
                foreach ($itemIds as $itemId) {
                    $invItem = Item::find($itemId);
                    if (!$invItem) continue;

                    if ($invItem->quantity < $saleItem->qty) {
                        throw new \Exception(
                            "Stok {$invItem->name} tidak cukup (tersisa {$invItem->quantity}, dibutuhkan {$saleItem->qty})."
                        );
                    }

                    $before = $invItem->quantity;
                    $invItem->decrement('quantity', $saleItem->qty);

                    StockTransaction::create([
                        'item_id'        => $invItem->id,
                        'type'           => 'OUT',
                        'quantity'       => $saleItem->qty,
                        'stock_before'   => $before,
                        'stock_after'    => $before - $saleItem->qty,
                        'date'           => now()->toDateString(),
                        'notes'          => "Invoice {$sale->invoice_number} — {$sale->recipient_name}",
                        'recorded_by_id' => auth()->id() ?? 1,
                    ]);
                }
            }

            $sale->update(['status' => 'sudah_dikirim']);

            return $sale->fresh('items');
        });
    }

    public function update(int $id, array $data): Sale
    {
        return DB::transaction(function () use ($id, $data) {
            $sale = Sale::with('items')->findOrFail($id);

            $sale->update([
                'recipient_name'    => $data['recipient_name'],
                'recipient_address' => $data['recipient_address'] ?? null,
                'invoice_date'      => $data['invoice_date'],
                'notes'             => $data['notes'] ?? null,
            ]);

            // Update items hanya kalau belum dikirim
            if ($sale->status === 'belum_dikirim' && !empty($data['items'])) {
                $sale->items()->delete();

                $grandTotal = 0;
                foreach ($data['items'] as $item) {
                    $total = $item['qty'] * $item['unit_price'];
                    $grandTotal += $total;
                    $sale->items()->create([
                        'item_name'          => $item['item_name'],
                        'description'        => $item['description'] ?? null,
                        'qty'                => $item['qty'],
                        'unit_price'         => $item['unit_price'],
                        'total_price'        => $total,
                        'inventory_item_ids' => $item['inventory_item_ids'] ?? null,
                    ]);
                }

                $sale->update(['grand_total' => $grandTotal]);
            }

            return $sale->fresh('items');
        });
    }

    public function recordPayment(int $id, int $amount): Sale
    {
        $sale = Sale::findOrFail($id);
        $sale->update(['paid_amount' => min($sale->paid_amount + $amount, $sale->grand_total)]);
        return $sale->fresh('items');
    }

    public function setPayment(int $id, int $amount): Sale
    {
        $sale = Sale::findOrFail($id);
        $sale->update(['paid_amount' => min($amount, $sale->grand_total)]);
        return $sale->fresh('items');
    }

    public function delete(int $id): void
    {
        $sale = Sale::findOrFail($id);

        if ($sale->status === 'sudah_dikirim') {
            throw new \Exception('Invoice yang sudah terkirim tidak dapat dihapus.');
        }

        $sale->delete();
    }

    private function generateInvoiceNumber(): string
    {
        $today  = now()->format('dmy');
        $prefix = "INV-{$today}";

        $last = Sale::where('invoice_number', 'like', "{$prefix}/%")
            ->orderByDesc('id')
            ->first();

        $seq = $last
            ? ((int) substr($last->invoice_number, strrpos($last->invoice_number, '/') + 1)) + 1
            : 1;

        return $prefix . '/' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
