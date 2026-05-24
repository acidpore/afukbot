<?php

namespace App\Modules\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index()
    {
        $items = $this->inventoryService->getAllItems();
        return $this->sendResponse($items, 'Items retrieved successfully');
    }

    public function valuasi()
    {
        return $this->sendResponse($this->inventoryService->getValuasi(), 'Valuasi retrieved');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'quantity'    => 'required|integer|min:0',
            'unit'        => 'required|string',
            'location'    => 'nullable|string',
            'harga_jual'  => 'nullable|integer|min:0',
        ]);

        $item = $this->inventoryService->createItem($request->all());
        return $this->sendResponse($item, 'Item created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $item = $this->inventoryService->updateItem($id, $request->all());
        return $this->sendResponse($item, 'Item updated successfully');
    }

    public function destroy($id)
    {
        $this->inventoryService->deleteItem($id);
        return $this->sendResponse(null, 'Item deleted successfully');
    }

    public function adjust(Request $request)
    {
        $request->validate([
            'item_id'  => 'required|exists:items,id',
            'type'     => 'required|in:IN,OUT',
            'quantity' => 'required|integer|min:1',
            'notes'    => 'nullable|string',
        ]);

        try {
            $result = $this->inventoryService->adjustStock(
                $request->item_id,
                $request->type,
                $request->quantity,
                $request->notes,
                auth()->id()
            );
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), [], 422);
        }

        return $this->sendResponse($result, 'Stock adjusted successfully');
    }

    public function transactions(Request $request)
    {
        $transactions = $this->inventoryService->getTransactions(50, $request->item_id);
        return $this->sendResponse($transactions, 'Transactions retrieved successfully');
    }

    public function categories()
    {
        $categories = $this->inventoryService->getAllCategories();
        return $this->sendResponse($categories, 'Categories retrieved');
    }

    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:categories,name']);
        $category = $this->inventoryService->createCategory($request->only('name'));
        return $this->sendResponse($category, 'Category created successfully', 201);
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.name'         => 'required|string',
            'items.*.category'     => 'required|string',
            'items.*.quantity'     => 'required|integer|min:0',
            'items.*.unit'         => 'required|string',
            'items.*.harga_jual'   => 'nullable|integer|min:0',
        ]);

        $result = $this->inventoryService->bulkCreateItems($request->items, auth()->id());
        return $this->sendResponse($result, 'Bulk import completed');
    }
}
