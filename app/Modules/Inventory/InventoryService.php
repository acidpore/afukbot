<?php

namespace App\Modules\Inventory;

use App\Models\Item;
use App\Models\Category;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function getAllItems()
    {
        return Item::with('category')->get();
    }

    public function getValuasi(): array
    {
        $items = Item::all();
        $total = $items->sum(fn($item) => $item->quantity * $item->harga_jual);
        return [
            'total_valuasi'  => $total,
            'total_item_jenis' => $items->count(),
            'total_stok'     => $items->sum('quantity'),
        ];
    }

    public function getAllCategories()
    {
        return Category::all();
    }

    public function createCategory(array $data)
    {
        return Category::create($data);
    }

    public function bulkCreateItems(array $rows): array
    {
        $created  = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $index => $row) {
            try {
                $categoryName = trim($row['category']);
                $category = Category::whereRaw('LOWER(name) = ?', [strtolower($categoryName)])->first()
                    ?? Category::create(['name' => $categoryName]);

                $itemName = trim($row['name']);
                $normalized = $this->normalizeName($itemName);

                $duplicate = Item::where('category_id', $category->id)
                    ->get()
                    ->contains(fn($item) => $this->normalizeName($item->name) === $normalized);

                if ($duplicate) {
                    $skipped++;
                    continue;
                }

                $qty = (int) $row['quantity'];

                $item = Item::create([
                    'name'        => $itemName,
                    'category_id' => $category->id,
                    'quantity'    => $qty,
                    'unit'        => trim($row['unit']),
                    'harga_jual'  => isset($row['harga_jual']) ? (int) $row['harga_jual'] : 0,
                ]);

                if ($qty > 0) {
                    StockTransaction::create([
                        'item_id'        => $item->id,
                        'type'           => 'IN',
                        'quantity'       => $qty,
                        'stock_before'   => 0,
                        'stock_after'    => $qty,
                        'date'           => now()->toDateString(),
                        'notes'          => 'Import dari CSV',
                        'recorded_by_id' => 1,
                    ]);
                }

                $created++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $index + 1, 'name' => $row['name'] ?? '-', 'reason' => $e->getMessage()];
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function normalizeName(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    }

    public function createItem(array $data)
    {
        return Item::create($data);
    }

    public function updateItem($id, array $data)
    {
        $item = Item::findOrFail($id);
        $item->update($data);
        return $item;
    }

    public function deleteItem($id)
    {
        $item = Item::findOrFail($id);
        return $item->delete();
    }

    public function adjustStock($itemId, $type, $quantity, $notes = null, $userId = null)
    {
        return DB::transaction(function () use ($itemId, $type, $quantity, $notes, $userId) {
            $item = Item::findOrFail($itemId);

            $stockBefore = $item->quantity;

            if ($type === 'IN') {
                $item->quantity += $quantity;
            } else {
                if ($item->quantity < $quantity) {
                    throw new \Exception("Stok tidak cukup. Stok saat ini: {$item->quantity}");
                }
                $item->quantity -= $quantity;
            }
            $item->save();

            $transaction = StockTransaction::create([
                'item_id'        => $itemId,
                'type'           => $type,
                'quantity'       => $quantity,
                'stock_before'   => $stockBefore,
                'stock_after'    => $item->quantity,
                'date'           => now()->toDateString(),
                'notes'          => $notes,
                'recorded_by_id' => $userId ?? 1,
            ]);

            return [
                'item'        => $item->fresh(['category']),
                'transaction' => $transaction,
            ];
        });
    }

    public function getTransactions($limit = 50, $itemId = null)
    {
        return StockTransaction::with(['item', 'item.category', 'recordedBy'])
            ->when($itemId, fn($q) => $q->where('item_id', $itemId))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
