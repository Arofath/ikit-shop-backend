<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStockMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductStockMovementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ProductStockMovement::with(['product', 'supplier']);

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'type'        => ['required', Rule::in(['IN', 'OUT', 'ADJUST'])],
            'quantity'    => 'required|integer|min:1',
            'cost_price'  => 'nullable|numeric|min:0',
            'note'        => 'nullable|string',
        ]);

        // ❗ Business rules
        if ($data['type'] !== 'IN') {
            $data['supplier_id'] = null;
            $data['cost_price'] = null;
        }

        $movement = ProductStockMovement::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Stock movement recorded',
            'data' => $movement
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductStockMovement $productStockMovement)
    {
        return response()->json([
            'success' => true,
            'data' => $productStockMovement->load(['product', 'supplier'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductStockMovement $productStockMovement)
    {
        $productStockMovement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock movement deleted'
        ]);
    }

    public function productStock(Product $product)
    {
        $stock = ProductStockMovement::where('product_id', $product->id)
            ->selectRaw("
                SUM(
                    CASE
                        WHEN type = 'IN' THEN quantity
                        WHEN type = 'OUT' THEN -quantity
                        WHEN type = 'ADJUST' THEN quantity
                    END
                ) as stock
            ")
            ->value('stock') ?? 0;

        return response()->json([
            'product_id' => $product->id,
            'stock' => $stock
        ]);
    }
}
