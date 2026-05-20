<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosController extends Controller
{
    /**
     * ១. API ទាញយក Category ដែលមានទំនិញលក់
     */
    public function getCategories()
    {
        $categories = Category::select('id', 'name')
            ->active()
            ->whereHas('products', function ($query) {
                $query->active();
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * ២. API ទាញយក Brand ដែលមានទំនិញលក់
     */
    public function getBrands()
    {
        $brands = Brand::select('id', 'name')
            ->active()
            ->whereHas('products', function ($query) {
                $query->active();
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }

    /**
     * ៣. API ស្វែងរកទំនិញ (មានមុខងារ Filter តាម Category & Brand)
     */
    public function searchProducts(Request $request)
    {
        $search = $request->query('query');
        $categoryId = $request->query('category_id');
        $brandId = $request->query('brand_id');

        $stockSubquery = ProductStockMovement::selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0)")
            ->whereColumn('product_stock_movements.product_id', 'products.id');

        $products = Product::select('id', 'name', 'sku', 'price', 'brand_id')
            ->selectSub($stockSubquery, 'current_stock')
            ->active()

            // ស្វែងរកតាមឈ្មោះ ឬ SKU
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })

            // Filter តាម Category
            ->when($categoryId, function ($query, $categoryId) {
                $query->whereHas('categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            })

            // Filter តាម Brand
            ->when($brandId, function ($query, $brandId) {
                $query->where('brand_id', $brandId);
            })

            ->with('thumbnail')
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * ៤. API ស្វែងរកអតិថិជន
     */
    public function searchUsers(Request $request)
    {
        $search = $request->query('query');

        $users = User::where('role', 'customer')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->select('id', 'name', 'email', 'phone')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * ៥. API រក្សាទុកវិក្កយបត្រ (Place Order)
     */
    public function storeOrder(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'shipping_name' => 'required_without:user_id|string|max:255',
            'shipping_phone' => 'nullable|string|max:20',
            'shipping_address' => 'nullable|string',
            'subtotal' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'shipping_fee' => 'nullable|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'payment_status' => 'required|in:UNPAID,PAID',
            'status' => 'required|in:PENDING,PROCESSING,COMPLETED,CANCELLED',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            $customerName = $request->shipping_name;
            if ($request->user_id && !$customerName) {
                $user = User::find($request->user_id);
                $customerName = $user->name;
            }

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $request->user_id,
                'shipping_name' => $customerName,
                'shipping_phone' => $request->shipping_phone,
                'shipping_address' => $request->shipping_address,
                'subtotal' => $request->subtotal,
                'discount' => $request->discount ?? 0,
                'shipping_fee' => $request->shipping_fee ?? 0,
                'grand_total' => $request->grand_total,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_status,
                'status' => $request->status,
                'notes' => 'Created via POS',
            ]);

            foreach ($request->items as $item) {
                $currentStock = ProductStockMovement::where('product_id', $item['product_id'])
                    ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0) as total")
                    ->value('total');

                if ($currentStock < $item['quantity']) {
                    throw new \Exception("Product ID {$item['product_id']} does not have enough stock.");
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                ]);

                ProductStockMovement::create([
                    'product_id' => $item['product_id'],
                    'type' => 'OUT',
                    'quantity' => $item['quantity'],
                    'reference_type' => 'ORDER',
                    'reference_id' => $order->id,
                    'notes' => 'POS Order: ' . $orderNumber,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => $order->load('orderItems.product')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 422);
        }
    }
}
