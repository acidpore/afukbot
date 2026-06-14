<?php

namespace App\Modules\Inventory;

use App\Models\Item;
use App\Models\StockCalibration;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CalibrationController extends Controller
{
    public function status()
    {
        $last = StockCalibration::latest('calibrated_at')->first();

        $daysSince = $last
            ? now()->diffInDays($last->calibrated_at)
            : null;

        return response()->json([
            'last_calibration' => $last ? [
                'id'             => $last->id,
                'calibrated_at'  => $last->calibrated_at,
                'total_items'    => $last->total_items,
                'total_adjusted' => $last->total_adjusted,
                'calibrated_by'  => $last->calibratedBy?->name,
            ] : null,
            'days_since' => $daysSince,
            'is_overdue' => $daysSince === null || $daysSince >= 7,
        ]);
    }

    public function history()
    {
        $records = StockCalibration::with('calibratedBy')
            ->orderByDesc('calibrated_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'calibrated_at'  => $c->calibrated_at,
                'calibrated_by'  => $c->calibratedBy?->name,
                'total_items'    => $c->total_items,
                'total_adjusted' => $c->total_adjusted,
                'notes'          => $c->notes,
            ]);

        return response()->json(['data' => $records]);
    }

    public function items()
    {
        $items = Item::with('category:id,name')
            ->select('id', 'name', 'category_id', 'quantity', 'unit', 'location')
            ->orderBy('name')
            ->get()
            ->map(fn($item) => [
                'id'           => $item->id,
                'name'         => $item->name,
                'category'     => $item->category?->name,
                'qty_system'   => $item->quantity,
                'qty_physical' => $item->quantity,
                'unit'         => $item->unit,
                'location'     => $item->location,
            ]);

        return response()->json(['data' => $items]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'notes'          => 'nullable|string|max:500',
            'items'          => 'required|array|min:1',
            'items.*.id'     => 'required|integer|exists:items,id',
            'items.*.qty_physical' => 'required|integer|min:0',
        ]);

        return DB::transaction(function () use ($data) {
            $userId    = Auth::id();
            $adjusted  = 0;
            $calItems  = [];

            foreach ($data['items'] as $row) {
                $item      = Item::findOrFail($row['id']);
                $qtySystem = $item->quantity;
                $qtyPhysical = (int) $row['qty_physical'];
                $delta     = $qtyPhysical - $qtySystem;

                $calItems[] = [
                    'item_id'      => $item->id,
                    'item_name'    => $item->name,
                    'qty_system'   => $qtySystem,
                    'qty_physical' => $qtyPhysical,
                    'delta'        => $delta,
                ];

                if ($delta === 0) continue;

                $adjusted++;
                $type = $delta > 0 ? 'IN' : 'OUT';

                $item->quantity = $qtyPhysical;
                $item->save();

                StockTransaction::create([
                    'item_id'        => $item->id,
                    'type'           => $type,
                    'quantity'       => abs($delta),
                    'stock_before'   => $qtySystem,
                    'stock_after'    => $qtyPhysical,
                    'date'           => now()->toDateString(),
                    'notes'          => 'Kalibrasi stok mingguan',
                    'recorded_by_id' => $userId,
                ]);
            }

            $calibration = StockCalibration::create([
                'calibrated_by'  => $userId,
                'calibrated_at'  => now()->toDateString(),
                'notes'          => $data['notes'] ?? null,
                'total_items'    => count($calItems),
                'total_adjusted' => $adjusted,
            ]);

            foreach ($calItems as $ci) {
                $calibration->items()->create($ci);
            }

            return response()->json([
                'message'        => "Kalibrasi selesai. {$adjusted} item disesuaikan.",
                'total_adjusted' => $adjusted,
                'calibration_id' => $calibration->id,
            ]);
        });
    }
}
