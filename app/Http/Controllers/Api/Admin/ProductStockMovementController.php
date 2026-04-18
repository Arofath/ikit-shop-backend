<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\ProductSerial;
use App\Http\Resources\ProductStockMovementResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductStockMovementController extends Controller
{
    // ១. មើលប្រវត្តិស្តុកទាំងអស់
    public function index(Request $request)
    {
        $query = ProductStockMovement::with(['product.images', 'supplier']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $movements = $query->latest()->paginate($request->get('limit', 15));

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
            'quantity'         => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->type === 'ADJUST' && $value === 0) {
                        $fail('For ADJUST, quantity cannot be 0. Use a positive number to add, or negative to deduct.');
                    } elseif ($request->type !== 'ADJUST' && $value < 1) {
                        $fail('For IN and OUT, quantity must be at least 1.');
                    }
                },
            ],
            
            'cost_price'       => 'required_if:type,IN|nullable|numeric|min:0',
            'note'             => 'nullable|string',
            'serials'          => 'nullable|array',
            'serials.*'        => 'string|distinct',
        ]);

        $product = Product::findOrFail($data['product_id']);

        if ($product->is_serialized) {
            // មិនទាមទារ Serial សម្រាប់ ADJUST ទេ ព្រោះយើងគ្រាន់តែកែតម្រូវលេខ
            if (in_array($data['type'], ['IN', 'OUT'])) {
                if (empty($data['serials']) || count($data['serials']) !== (int) $data['quantity']) {
                    return $this->sendError('Validation Error.', ['The number of serials must exactly match the quantity for serialized products.'], 422);
                }
            }
        } else {
            $data['serials'] = [];
        }

        DB::beginTransaction();

        try {
            $currentStock = $this->calculateStock($product->id);

            // 🌟 ១. គណនាការផ្លាស់ប្តូរ (Change) ឱ្យបានច្បាស់លាស់មុននឹង Save
            $change = 0;
            if ($data['type'] === 'IN') {
                $change = clone $data['quantity']; // បូកបញ្ជូល
            } elseif ($data['type'] === 'OUT') {
                $change = -$data['quantity']; // ដកចេញ (ព្រោះ Frontend បញ្ជូនលេខវិជ្ជមាន)
            } elseif ($data['type'] === 'ADJUST') {
                $change = clone $data['quantity']; // យកតាមអ្វីដែលវាយចូល (អាច + ឬ -)
            }

            // គណនា Balance ទុកជាមុន
            $data['balance_after'] = $currentStock + $change;

            // 🌟 ២. ការពារកុំឱ្យកាត់ស្តុករហូតដល់អស់ (Negative Balance) ដែលជាដើមហេតុធ្វើឱ្យ Error 500
            if ($data['balance_after'] < 0) {
                DB::rollBack();
                return $this->sendError('Stock insufficient.', ["Cannot deduct below 0. Current available stock is {$currentStock}."], 422);
            }

            // ឆែក Serial ពេលលក់ចេញ (OUT)
            if ($data['type'] === 'OUT' && $product->is_serialized) {
                $validSerials = ProductSerial::whereIn('serial_number', $data['serials'])
                    ->where('product_id', $product->id)
                    ->where('status', 'AVAILABLE')
                    ->count();

                if ($validSerials !== count($data['serials'])) {
                    DB::rollBack();
                    return $this->sendError('Some serial numbers are invalid or already sold.', [], 422);
                }
            }

            // បង្កើត Stock Movement Record
            $movement = ProductStockMovement::create($data);

            // ចាត់ចែង Serial Numbers (សម្រាប់ IN នឹង OUT)
            if ($product->is_serialized && !empty($data['serials'])) {
                if ($data['type'] === 'IN') {
                    $serialData = [];
                    foreach ($data['serials'] as $sn) {
                        $serialData[] = [
                            'id'                  => (string) Str::uuid(),
                            'product_id'          => $product->id,
                            'initial_movement_id' => $movement->id,
                            'serial_number'       => $sn,
                            'status'              => 'AVAILABLE',
                            'created_at'          => now(),
                            'updated_at'          => now(),
                        ];
                    }
                    ProductSerial::insert($serialData);
                } elseif ($data['type'] === 'OUT') {
                    ProductSerial::whereIn('serial_number', $data['serials'])
                        ->where('product_id', $product->id)
                        ->update([
                            'status'           => 'SOLD',
                            'sold_movement_id' => $movement->id
                        ]);
                }
            }

            DB::commit();

            return $this->sendResponse(
                new ProductStockMovementResource($movement),
                'Stock movement recorded successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            // បង្ហាញ Error Message ច្បាស់ៗដើម្បីស្រួល Debug
            return $this->sendError('Transaction Failed.', [$e->getMessage()], 500);
        }
    }

    // ៣. មើលលម្អិតនៃប្រតិបត្តិការនីមួយៗ
    public function show($id)
    {
        $movement = ProductStockMovement::with(['product', 'supplier'])->findOrFail($id);
        return $this->sendResponse(new ProductStockMovementResource($movement), 'Stock movement retrieved.');
    }

    // ៤. លុប (បានតែ Record ចុងក្រោយ)
    public function destroy(string $id)
    {
        $movement = ProductStockMovement::findOrFail($id);

        $isLatest = !ProductStockMovement::where('product_id', $movement->product_id)
            ->where('created_at', '>', $movement->created_at)
            ->exists();

        if (!$isLatest) {
            return $this->sendError('Action Denied.', ['Only the latest record can be deleted to maintain balance integrity.'], 403);
        }

        DB::beginTransaction();
        try {
            // 🌟 ត្រូវកែប្រែ Serial មុននឹងលុប Movement
            if ($movement->type === 'IN') {
                // បើលុបស្តុកទិញចូល ត្រូវលុប Serial ដែលចូលមកពេលនោះចោលវិញ
                ProductSerial::where('initial_movement_id', $movement->id)->delete();
            } elseif ($movement->type === 'OUT') {
                // បើលុបស្តុកលក់ចេញ ត្រូវប្រគល់ Serial នោះឱ្យទំនេរវិញ
                ProductSerial::where('sold_movement_id', $movement->id)->update([
                    'status' => 'AVAILABLE',
                    'sold_movement_id' => null
                ]);
            }

            $movement->delete();

            DB::commit();
            return $this->sendResponse([], 'Stock movement deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Deletion Failed.', [$e->getMessage()], 500);
        }
    }

    // ៥. មុខងារពិសេស៖ របាយការណ៍សង្ខេប (Stock Summary Report) - 🌟 Optimized ខ្លាំងបំផុត
    public function stockReport()
    {
        // ប្រើប្រាស់ Subquery នៅក្នុង Database ផ្ទាល់ កាត់បន្ថយ N+1 Query
        $lowStockProducts = Product::select('products.*')
            ->selectSub(function ($query) {
                $query->selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0)")
                    ->from('product_stock_movements')
                    ->whereColumn('product_stock_movements.product_id', 'products.id');
            }, 'current_stock')
            ->having('current_stock', '<=', 5)
            ->with(['category', 'brand']) // ភ្ជាប់មកជាមួយបើចាំបាច់
            ->get();

        return $this->sendResponse([
            'low_stock_items' => $lowStockProducts
        ], 'Stock report retrieved.');
    }

    // Helper Method សម្រាប់គណនាស្តុក (រក្សាទុកដដែល)
    private function calculateStock($productId)
    {
        return ProductStockMovement::where('product_id', $productId)
            ->selectRaw("SUM(CASE 
            WHEN type IN ('IN', 'ADJUST') THEN quantity 
            WHEN type = 'OUT' THEN -quantity 
            ELSE 0 END) as total")
            ->value('total') ?? 0;
    }
}
