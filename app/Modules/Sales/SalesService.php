<?php

namespace App\Modules\Sales;

use App\Models\Sale;
use App\Models\Item;
use App\Models\Category;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

            $paidAmount = isset($data['paid_amount']) ? (int) $data['paid_amount'] : 0;

            $sale = Sale::create([
                'invoice_number'    => $invoiceNumber,
                'recipient_name'    => $data['recipient_name'],
                'recipient_address' => $data['recipient_address'] ?? null,
                'invoice_date'      => $data['invoice_date'],
                'shipped_at'        => !empty($data['shipped_at']) ? $data['shipped_at'] : null,
                'notes'             => $data['notes'] ?? null,
                'grand_total'       => $grandTotal,
                'paid_amount'       => $paidAmount,
                'status'            => 'rencana',
            ]);

            foreach ($data['items'] as $item) {
                $inventoryItemIds = $item['inventory_item_ids'] ?? null;

                if (!empty($item['is_new_item'])) {
                    $inventoryItemIds = [$this->autoCreateInventoryItem(
                        $item['item_name'],
                        (int) $item['qty'],
                        (int) $item['unit_price'],
                        auth()->id(),
                        $sale->invoice_number,
                    )];
                }

                $sale->items()->create([
                    'item_name'           => $item['item_name'],
                    'description'         => $item['description'] ?? null,
                    'qty'                 => $item['qty'],
                    'unit_price'          => $item['unit_price'],
                    'total_price'         => $item['qty'] * $item['unit_price'],
                    'inventory_item_ids'  => $inventoryItemIds,
                ]);
            }

            return $sale->load('items');
        });
    }

    public function markAsShipped(int $id, ?string $shippedAt = null, ?string $notes = null): Sale
    {
        return DB::transaction(function () use ($id, $shippedAt, $notes) {
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

            $updateData = [
                'status'     => 'sudah_dikirim',
                'shipped_at' => $shippedAt ? \Carbon\Carbon::parse($shippedAt) : now(),
            ];
            if ($notes !== null) {
                $updateData['notes'] = $notes;
            }
            $sale->update($updateData);

            return $sale->fresh('items');
        });
    }

    public function update(int $id, array $data): Sale
    {
        return DB::transaction(function () use ($id, $data) {
            $sale = Sale::with('items')->findOrFail($id);

            $updatePayload = [
                'recipient_name'    => $data['recipient_name'],
                'recipient_address' => $data['recipient_address'] ?? null,
                'invoice_date'      => $data['invoice_date'],
                'shipped_at'        => array_key_exists('shipped_at', $data) ? ($data['shipped_at'] ?: null) : $sale->shipped_at,
                'notes'             => $data['notes'] ?? null,
                'sender_name'       => $data['sender_name'] ?? null,
                'sender_address'    => $data['sender_address'] ?? null,
            ];
            if (array_key_exists('paid_amount', $data)) {
                $currentGrandTotal = $sale->grand_total;
                $updatePayload['paid_amount'] = (int) $data['paid_amount'];
            }
            $sale->update($updatePayload);

            // Update items hanya kalau stok belum dipotong
            if ($sale->status === 'rencana' && !empty($data['items'])) {
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
        $sale->update(['paid_amount' => $sale->paid_amount + $amount]);
        return $sale->fresh('items');
    }

    public function setPayment(int $id, int $amount): Sale
    {
        $sale = Sale::findOrFail($id);
        $sale->update(['paid_amount' => $amount]);
        return $sale->fresh('items');
    }

    public function revertStock(int $id): Sale
    {
        return DB::transaction(function () use ($id) {
            $sale = Sale::with('items')->findOrFail($id);

            if ($sale->status !== 'sudah_dikirim') {
                throw new \Exception('Invoice ini belum berstatus terkirim.');
            }

            foreach ($sale->items as $saleItem) {
                $itemIds = $saleItem->inventory_item_ids ?? [];
                foreach ($itemIds as $itemId) {
                    $invItem = Item::find($itemId);
                    if (!$invItem) continue;

                    $before = $invItem->quantity;
                    $invItem->increment('quantity', $saleItem->qty);

                    StockTransaction::create([
                        'item_id'        => $invItem->id,
                        'type'           => 'IN',
                        'quantity'       => $saleItem->qty,
                        'stock_before'   => $before,
                        'stock_after'    => $before + $saleItem->qty,
                        'date'           => now()->toDateString(),
                        'notes'          => "Revert stok — Invoice {$sale->invoice_number} ({$sale->recipient_name})",
                        'recorded_by_id' => auth()->id() ?? 1,
                    ]);
                }
            }

            $sale->update(['status' => 'rencana', 'shipped_at' => null]);

            return $sale->fresh('items');
        });
    }

    public function uploadAttachment(int $id, \Illuminate\Http\UploadedFile $file): Sale
    {
        $sale = Sale::findOrFail($id);
        if ($sale->attachment_path) {
            Storage::disk('public')->delete($sale->attachment_path);
        }
        $path = $file->store('sale-attachments', 'public');
        $sale->update(['attachment_path' => $path]);
        return $sale->fresh('items');
    }

    public function deleteAttachment(int $id): Sale
    {
        $sale = Sale::findOrFail($id);
        if ($sale->attachment_path) {
            Storage::disk('public')->delete($sale->attachment_path);
            $sale->update(['attachment_path' => null]);
        }
        return $sale->fresh('items');
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $sale = Sale::with('items')->findOrFail($id);

            if ($sale->status === 'sudah_dikirim') {
                $this->revertStock($id);
                $sale->refresh();
            }

            $sale->delete();
        });
    }

    private function autoCreateInventoryItem(string $name, int $qty, int $hargaJual, int $userId, string $invoiceNumber): int
    {
        $category = Category::firstOrCreate(['name' => 'Lainnya']);

        $item = Item::create([
            'name'        => $name,
            'category_id' => $category->id,
            'quantity'    => $qty,
            'unit'        => 'pcs',
            'location'    => 'Ruko',
            'harga_jual'  => $hargaJual,
        ]);

        if ($qty > 0) {
            StockTransaction::create([
                'item_id'        => $item->id,
                'type'           => 'IN',
                'quantity'       => $qty,
                'stock_before'   => 0,
                'stock_after'    => $qty,
                'date'           => now()->toDateString(),
                'notes'          => "Stok awal - auto dari {$invoiceNumber}",
                'recorded_by_id' => $userId,
            ]);
        }

        return $item->id;
    }

    public function getPendingItems(): array
    {
        $sales = Sale::with('items')
            ->whereIn('status', ['rencana', 'proses'])
            ->where('paid_amount', '>', 0)
            ->orderBy('invoice_date')
            ->get();

        $itemMap = [];
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $key = strtolower(trim($item->item_name));
                if (!isset($itemMap[$key])) {
                    $itemMap[$key] = [
                        'item_name'          => $item->item_name,
                        'total_qty'          => 0,
                        'inventory_item_ids' => [],
                        'invoices'           => [],
                    ];
                }
                $itemMap[$key]['total_qty'] += $item->qty;
                foreach ($item->inventory_item_ids ?? [] as $id) {
                    if (!in_array($id, $itemMap[$key]['inventory_item_ids'])) {
                        $itemMap[$key]['inventory_item_ids'][] = $id;
                    }
                }
                $itemMap[$key]['invoices'][] = [
                    'invoice_number' => $sale->invoice_number,
                    'recipient_name' => $sale->recipient_name,
                    'qty'            => $item->qty,
                ];
            }
        }

        $allInventoryIds = [];
        foreach ($itemMap as $entry) {
            foreach ($entry['inventory_item_ids'] as $id) {
                $allInventoryIds[] = $id;
            }
        }
        $inventoryStock = Item::whereIn('id', array_unique($allInventoryIds))
            ->get()
            ->keyBy('id');

        foreach ($itemMap as &$entry) {
            if (!empty($entry['inventory_item_ids'])) {
                $entry['stok'] = collect($entry['inventory_item_ids'])
                    ->sum(fn($id) => (int) ($inventoryStock[$id]->quantity ?? 0));
            } else {
                $entry['stok'] = null;
            }
        }
        unset($entry);

        usort($itemMap, fn($a, $b) => $b['total_qty'] - $a['total_qty']);

        return [
            'items'          => array_values($itemMap),
            'total_invoices' => $sales->count(),
        ];
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
