<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStockMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ProductStockMovementResource;
use App\Models\ProductSerial;


class ProductStockMovementController extends Controller
{
    // ១. មើលប្រវត្តិស្តុកទាំងអស់
    public function index(Request $request)
    {
        $query = ProductStockMovement::with(['product', 'supplier']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $movements = $query->latest()->paginate($request->limit ?? 15);

        return $this->sendResponse(
            ProductStockMovementResource::collection($movements)->response()->getData(true),
            'Stock movements retrieved successfully.'
        );
    }

    // ២. ការបញ្ចូលស្តុកថ្មី
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'       => 'required|exists:products,id',
            'supplier_id'      => 'required_if:type,IN|nullable|exists:suppliers,id',
            'reference_number' => 'nullable|string|max:50',
            'type'             => ['required', Rule::in(['IN', 'OUT', 'ADJUST'])],
            'quantity'         => 'required|integer|min:1',
            'cost_price'       => 'required_if:type,IN|nullable|numeric|min:0',
            'note'             => 'nullable|string',
            'serials'          => 'required_if:type,IN,OUT|array',
            'serials.*'        => 'string|distinct',
        ]);

        try {
            return DB::transaction(function () use ($data, $request) {
                $product = Product::findOrFail($data['product_id']);
                $currentStock = $this->calculateStock($product->id);

                // ១. ឆែកលក្ខខណ្ឌពេលលក់ចេញ (OUT)
                if ($data['type'] === 'OUT') {
                    if ($currentStock < $data['quantity']) {
                        return $this->sendError('Stock insufficient.', ["Current available: {$currentStock}"], 422);
                    }

                    // ឆែកមើលថា តើ Serial ដែលចង់លក់មានក្នុងស្តុកពិតមែនឬអត់
                    $validSerials = ProductSerial::whereIn('serial_number', $data['serials'])
                        ->where('product_id', $product->id)
                        ->where('status', 'AVAILABLE')
                        ->count();

                    if ($validSerials !== count($data['serials'])) {
                        return $this->sendError('Some serial numbers are invalid or already sold.', [], 422);
                    }
                }

                // ២. បង្កើត Stock Movement Record
                $change = ($data['type'] === 'OUT') ? -$data['quantity'] : $data['quantity'];
                $data['balance_after'] = $currentStock + $change;
                $movement = ProductStockMovement::create($data);

                // ៣. ចាត់ចែង Serial Numbers តាមប្រភេទប្រតិបត្តិការ
                if ($data['type'] === 'IN') {
                    foreach ($data['serials'] as $sn) {
                        ProductSerial::create([
                            'product_id'          => $product->id,
                            'initial_movement_id' => $movement->id,
                            'serial_number'       => $sn,
                            'status'              => 'AVAILABLE',
                        ]);
                    }
                } elseif ($data['type'] === 'OUT') {
                    ProductSerial::whereIn('serial_number', $data['serials'])
                        ->update([
                            'status'           => 'SOLD',
                            'sold_movement_id' => $movement->id
                        ]);
                }

                return $this->sendResponse(
                    new ProductStockMovementResource($movement),
                    'Stock and Serial Numbers recorded successfully.'
                );
            });
        } catch (\Exception $e) {
            return $this->sendError('Transaction Failed', [$e->getMessage()], 500);
        }
    }

    // ៣. មើលលម្អិតនៃប្រតិបត្តិការនីមួយៗ
    public function show($id)
    {
        $movement = ProductStockMovement::with(['product', 'supplier'])->findOrFail($id);
        return $this->sendResponse(new ProductStockMovementResource($movement), 'Stock movement retrieved.');
    }
    

    // ៤. លុប (បានតែ Record ចុងក្រោយ)
    public function destroy(ProductStockMovement $productStockMovement)
    {
        // Algorithm: ឆែកមើលថាវាជា Record ចុងក្រោយរបស់ Product នោះឬអត់
        $isLatest = !ProductStockMovement::where('product_id', $productStockMovement->product_id)
            ->where('created_at', '>', $productStockMovement->created_at)
            ->exists();

        if (!$isLatest) {
            return $this->sendError('Action Denied.', ['Only the latest record can be deleted to maintain balance integrity.'], 403);
        }

        $productStockMovement->delete();

        return $this->sendResponse([], 'Stock movement deleted successfully.');
    }

    // ៥. មុខងារពិសេស៖ របាយការណ៍សង្ខេប (Stock Summary Report)
    public function stockReport()
    {
        // ឧទាហរណ៍៖ ទាញយកផលិតផលដែលជិតអស់ពីស្តុក (Low Stock)
        $lowStock = Product::whereHas('stockMovements')
            ->get()
            ->filter(function ($product) {
                return $product->current_stock <= 5;
            });

        return response()->json([
            'success' => true,
            'low_stock_items' => $lowStock
        ]);
    }

    private function calculateStock($productId)
    {
        return ProductStockMovement::where('product_id', $productId)
            ->selectRaw("SUM(CASE 
            WHEN type = 'IN' THEN quantity 
            WHEN type = 'OUT' THEN -quantity 
            WHEN type = 'ADJUST' THEN quantity 
            ELSE 0 END) as total")
            ->value('total') ?? 0;
    }
}

//រៀបចំកូដក្នុង Model Product ដើម្បីឱ្យវាអាចហៅ $product->current_stock ដែរ