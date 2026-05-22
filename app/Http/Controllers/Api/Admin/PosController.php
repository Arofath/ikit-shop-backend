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

        $products = Product::select('id', 'name', 'sku', 'price', 'brand_id', 'created_at')
            ->selectSub($stockSubquery, 'current_stock')
            ->active()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($categoryId, function ($query, $categoryId) {
                $query->whereHas('categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            })
            ->when($brandId, function ($query, $brandId) {
                $query->where('brand_id', $brandId);
            })
            ->with('thumbnail')
            ->latest() // 🌟 ១. តម្រៀបទំនិញថ្មីឱ្យនៅខាងលើគេ
            ->paginate(20); // 🌟 ២. កាត់យកម្តង ២០ មុខ ជំនួសឱ្យពាក្យ take(20)->get()

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
        // 🌟 ១. Validation លក្ខខណ្ឌតឹងរ៉ឹង
        $request->validate([
            'user_id'          => 'nullable|exists:users,id',
            'shipping_name'    => 'required_without:user_id|string|max:255',
            'shipping_phone'   => 'nullable|string|max:20',
            'city'             => 'nullable|string|max:255',
            'shipping_address' => 'nullable|string',
            'discount'         => 'nullable|numeric|min:0',
            'shipping_fee'     => 'nullable|numeric|min:0', // សម្រាប់ករណី Admin ចង់វាយបញ្ចូលដោយដៃ
            'payment_method'   => 'required|string',
            'payment_status'   => 'required|in:UNPAID,PAID',
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            // កំណត់ឈ្មោះអតិថិជន
            $customerName = $request->shipping_name;
            if ($request->user_id && !$customerName) {
                $user = User::find($request->user_id);
                $customerName = $user->name;
            }

            // 🌟 ២. កំណត់លក្ខខណ្ឌ Walk-in vs Social Order
            $isWalkIn = empty($request->shipping_address) && empty($request->city);

            // 🌟 ៣. គណនាថ្លៃដឹកជញ្ជូន
            $shippingFee = 0;
            if ($isWalkIn) {
                // បើ Walk-in មិនគិតថ្លៃដឹក
                $shippingFee = 0;
            } else {
                // បើ Social Order (មានអាសយដ្ឋាន/ខេត្ត)
                if (!empty($request->city)) {
                    // បើមានរើសខេត្ត គណនាស្វ័យប្រវត្តិ
                    $shippingFee = $this->calculateShippingFee($request->city);
                } else {
                    // បើអត់រើសខេត្ត តែ Admin បញ្ចូលដោយដៃ
                    $shippingFee = $request->shipping_fee ?? 0;
                }
            }

            // 🌟 ៤. គណនាលុយទំនិញសរុប (Subtotal Re-calculation)
            $subtotal = 0;
            $orderItemsData = [];

            foreach ($request->items as $item) {
                // ឆែកស្តុក
                $currentStock = ProductStockMovement::where('product_id', $item['product_id'])
                    ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0) as total")
                    ->value('total');

                if ($currentStock < $item['quantity']) {
                    throw new \Exception("Product ID {$item['product_id']} does not have enough stock.");
                }

                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $subtotal += $itemSubtotal;

                $orderItemsData[] = [
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal'   => $itemSubtotal,
                ];

                // កាត់ស្តុក (Stock Move)
                ProductStockMovement::create([
                    'product_id'       => $item['product_id'],
                    'reference_number' => $orderNumber, // ភ្ជាប់វាទៅ Order តាមរយៈ Order Number
                    'type'             => 'OUT',
                    'quantity'         => $item['quantity'],
                    'cost_price'       => 0,
                    'balance_after'    => $currentStock - $item['quantity'],
                    'note'             => 'POS Order: ' . $orderNumber, // ✅ ដូរពី notes មក note វិញ
                ]);
            }

            // 🌟 ៥. គណនា Grand Total
            $discount = $request->discount ?? 0;
            $grandTotal = ($subtotal - $discount) + $shippingFee;
            if ($grandTotal < 0) $grandTotal = 0;

            // 🌟 ៦. កំណត់ Status ឆ្លាតវៃ
            $orderStatus = 'PENDING';
            if ($isWalkIn && $request->payment_status === 'PAID') {
                $orderStatus = 'COMPLETED';
            }

            // 🌟 ៧. បង្កើត Order មេ
            $order = Order::create([
                'order_number'     => $orderNumber,
                'user_id'          => $request->user_id,
                'shipping_name'    => $customerName,
                'shipping_phone'   => $request->shipping_phone,
                'city'             => $request->city,
                'shipping_address' => $request->shipping_address,
                'subtotal'         => $subtotal,
                'discount'         => $discount,
                'shipping_fee'     => $shippingFee,
                'grand_total'      => $grandTotal,
                'payment_method'   => $request->payment_method,
                'payment_status'   => $request->payment_status,
                'status'           => $orderStatus,
                'notes'            => $isWalkIn ? 'POS Walk-in Order' : 'POS Social Order',
            ]);

            // បញ្ចូល Order ID ទៅក្នុង OrderItems ដែលបានរៀបចំ
            foreach ($orderItemsData as $itemData) {
                $itemData['order_id'] = $order->id;
                OrderItem::create($itemData);
            }

            // Update reference_id ក្នុង ProductStockMovement
            // ProductStockMovement::where('notes', 'POS Order: ' . $orderNumber)
            //     ->update(['reference_id' => $order->id]);

            // 🌟 ៨. បង្កើតកំណត់ត្រា Payment
            $order->payment()->create([
                'amount'         => $grandTotal,
                'payment_method' => $request->payment_method,
                'status'         => $request->payment_status,
            ]);

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

    /**
     * Helper Function: គណនាថ្លៃដឹកជញ្ជូនស្វ័យប្រវត្តិ
     */
    private function calculateShippingFee($city)
    {
        if (empty($city)) return 0;

        $cityName = strtolower(trim($city));
        // បើនៅភ្នំពេញ យក ២ ដុល្លារ, ខេត្តផ្សេង យក ២.៥ ដុល្លារ
        return ($cityName === 'phnom penh') ? 2.00 : 2.50;
    }
}
