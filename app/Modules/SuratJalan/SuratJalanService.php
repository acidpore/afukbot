<?php

namespace App\Modules\SuratJalan;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SuratJalan;
use App\Models\SuratJalanItem;
use App\Models\Item;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;

class SuratJalanService
{
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return SuratJalan::with(['sale.items', 'items.saleItem'])
            ->orderByDesc('tanggal_kirim')
            ->orderByDesc('id')
            ->get()
            ->map(fn($sj) => $this->appendShipmentProgress($sj));
    }

    public function getBySaleId(int $saleId): array
    {
        $sjs = SuratJalan::with(['items.saleItem'])
            ->where('sale_id', $saleId)
            ->orderBy('id')
            ->get();

        $sale = Sale::with('items')->findOrFail($saleId);
        $progress = $this->calcProgress($sale);

        return [
            'surat_jalans' => $sjs,
            'progress'     => $progress,
        ];
    }

    public function create(array $data): SuratJalan
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::with('items')->findOrFail($data['sale_id']);

            if ($sale->status === 'sudah_dikirim') {
                throw new \Exception('Invoice ini sudah berstatus terkirim.');
            }

            // Validate each item qty_kirim <= sisa
            $progress = $this->calcProgress($sale);
            foreach ($data['items'] as $row) {
                $saleItemId = $row['sale_item_id'];
                $sisa = $progress['items'][$saleItemId]['qty_sisa'] ?? 0;
                if ($row['qty_kirim'] > $sisa) {
                    $name = $progress['items'][$saleItemId]['item_name'] ?? "Item #{$saleItemId}";
                    throw new \Exception("Qty kirim untuk {$name} melebihi sisa ({$sisa}).");
                }
                if ($row['qty_kirim'] <= 0) {
                    throw new \Exception("Qty kirim harus lebih dari 0.");
                }
            }

            $nomor = $this->generateNomor();

            $sj = SuratJalan::create([
                'sale_id'       => $sale->id,
                'nomor_sj'      => $nomor,
                'tanggal_kirim' => $data['tanggal_kirim'],
                'catatan'       => $data['catatan'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $saleItem = SaleItem::find($row['sale_item_id']);
                if (!$saleItem) continue;

                SuratJalanItem::create([
                    'surat_jalan_id' => $sj->id,
                    'sale_item_id'   => $saleItem->id,
                    'qty_kirim'      => $row['qty_kirim'],
                ]);

                // Deduct stock
                foreach ($saleItem->inventory_item_ids ?? [] as $itemId) {
                    $invItem = Item::find($itemId);
                    if (!$invItem) continue;

                    $before = $invItem->quantity;
                    $invItem->decrement('quantity', $row['qty_kirim']);

                    StockTransaction::create([
                        'item_id'        => $invItem->id,
                        'type'           => 'OUT',
                        'quantity'       => $row['qty_kirim'],
                        'stock_before'   => $before,
                        'stock_after'    => $before - $row['qty_kirim'],
                        'date'           => $data['tanggal_kirim'],
                        'notes'          => "SJ {$nomor} — Invoice {$sale->invoice_number} ({$sale->recipient_name})",
                        'recorded_by_id' => auth()->id() ?? 1,
                    ]);
                }
            }

            // Auto-mark invoice as sudah_dikirim if all items fully shipped
            $freshProgress = $this->calcProgress($sale->fresh('items'));
            if ($freshProgress['qty_total_sisa'] === 0) {
                $sale->update([
                    'status'     => 'sudah_dikirim',
                    'shipped_at' => now(),
                ]);
            }

            return $sj->load('items.saleItem');
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $sj = SuratJalan::with(['items.saleItem', 'sale'])->findOrFail($id);

            // Revert stock
            foreach ($sj->items as $sjItem) {
                $saleItem = $sjItem->saleItem;
                if (!$saleItem) continue;

                foreach ($saleItem->inventory_item_ids ?? [] as $itemId) {
                    $invItem = Item::find($itemId);
                    if (!$invItem) continue;

                    $before = $invItem->quantity;
                    $invItem->increment('quantity', $sjItem->qty_kirim);

                    StockTransaction::create([
                        'item_id'        => $invItem->id,
                        'type'           => 'IN',
                        'quantity'       => $sjItem->qty_kirim,
                        'stock_before'   => $before,
                        'stock_after'    => $before + $sjItem->qty_kirim,
                        'date'           => now()->toDateString(),
                        'notes'          => "Revert SJ {$sj->nomor_sj} — Invoice {$sj->sale->invoice_number}",
                        'recorded_by_id' => auth()->id() ?? 1,
                    ]);
                }
            }

            // Revert sale status if it was fully shipped
            if ($sj->sale->status === 'sudah_dikirim') {
                $sj->sale->update(['status' => 'rencana', 'shipped_at' => null]);
            }

            $sj->delete();
        });
    }

    public function getCompletedInvoices(): array
    {
        $sales = Sale::with(['items', 'suratJalans.items.saleItem'])
            ->where('status', 'sudah_dikirim')
            ->whereHas('suratJalans')
            ->orderByDesc('shipped_at')
            ->orderByDesc('id')
            ->get();

        return $sales->map(function ($sale) {
            return [
                'id'             => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'recipient_name' => $sale->recipient_name,
                'invoice_date'   => $sale->invoice_date,
                'shipped_at'     => $sale->shipped_at,
                'grand_total'    => $sale->grand_total,
                'surat_jalans'   => $sale->suratJalans->map(fn($sj) => [
                    'id'            => $sj->id,
                    'nomor_sj'      => $sj->nomor_sj,
                    'tanggal_kirim' => $sj->tanggal_kirim,
                    'catatan'       => $sj->catatan,
                    'items'         => $sj->items->map(fn($i) => [
                        'id'        => $i->id,
                        'qty_kirim' => $i->qty_kirim,
                        'sale_item' => $i->saleItem ? ['item_name' => $i->saleItem->item_name] : null,
                    ])->values(),
                ])->values(),
            ];
        })->toArray();
    }

    public function getInvoicesWithProgress(): array
    {
        $sales = Sale::with(['items', 'suratJalans.items'])
            ->whereIn('status', ['rencana', 'proses'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        return $sales->map(function ($sale) {
            $progress = $this->calcProgress($sale);
            $data = $sale->toArray();
            // Pastikan inventory_item_ids ikut di-serialize per item
            $data['items'] = $sale->items->map(fn($item) => [
                'id'                  => $item->id,
                'item_name'           => $item->item_name,
                'qty'                 => $item->qty,
                'unit_price'          => $item->unit_price,
                'inventory_item_ids'  => $item->inventory_item_ids ?? [],
            ])->values()->toArray();
            $data['paid_amount'] = (float) ($sale->paid_amount ?? 0);
            $data['progress'] = $progress;
            return $data;
        })->toArray();
    }

    private function calcProgress(Sale $sale): array
    {
        $items = [];
        $qtyTotalOrder = 0;
        $qtyTotalKirim = 0;

        foreach ($sale->items as $saleItem) {
            $shipped = SuratJalanItem::where('sale_item_id', $saleItem->id)->sum('qty_kirim');
            $sisa    = $saleItem->qty - $shipped;

            $items[$saleItem->id] = [
                'item_name'  => $saleItem->item_name,
                'qty_order'  => $saleItem->qty,
                'qty_kirim'  => (int) $shipped,
                'qty_sisa'   => (int) $sisa,
            ];

            $qtyTotalOrder += $saleItem->qty;
            $qtyTotalKirim += $shipped;
        }

        return [
            'items'            => $items,
            'qty_total_order'  => $qtyTotalOrder,
            'qty_total_kirim'  => (int) $qtyTotalKirim,
            'qty_total_sisa'   => (int) ($qtyTotalOrder - $qtyTotalKirim),
        ];
    }

    private function appendShipmentProgress(SuratJalan $sj): SuratJalan
    {
        return $sj;
    }

    private function generateNomor(): string
    {
        $today  = now()->format('dmy');
        $prefix = "SJ-{$today}";

        $last = SuratJalan::where('nomor_sj', 'like', "{$prefix}/%")
            ->orderByDesc('id')
            ->first();

        $seq = $last
            ? ((int) substr($last->nomor_sj, strrpos($last->nomor_sj, '/') + 1)) + 1
            : 1;

        return $prefix . '/' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
