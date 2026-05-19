<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\User;
use App\Notifications\OrderStatusUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * ១. បង្ហាញវិក្កយបត្រទាំងអស់ (មានមុខងារ Filter តាម Status)
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'payment', 'items'])->latest();

        // 🌟 Admin អាច Filter មើលតែ Order ណាដែល PENDING ឬ COMPLETED បាន
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_status') && $request->payment_status != '') {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where('order_number', 'LIKE', "%{$search}%")
                ->orWhere('shipping_name', 'LIKE', "%{$search}%");
        }

        // បែងចែក ១៥ វិក្កយបត្រក្នុងមួយទំព័រ
        $orders = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => AdminOrderResource::collection($orders)
        ]);
    }

    /**
     * ២. មើលព័ត៌មានលម្អិតនៃវិក្កយបត្រណាមួយ
     */
    public function show($id)
    {
        $order = Order::with(['user', 'items.product.thumbnail', 'payment'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new AdminOrderResource($order)
        ]);
    }

    /**
     * ៣. មុខងារផ្លាស់ប្តូរស្ថានភាពវិក្កយបត្រ (បេះដូងនៃ Admin Order Flow)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:PENDING,PROCESSING,SHIPPED,COMPLETED,CANCELLED'
        ]);

        $order = Order::with(['items', 'payment'])->findOrFail($id);

        // ការពារកុំឱ្យ Admin ចុចដូរ Status វិក្កយបត្រដែលបិទបញ្ជីរួច (COMPLETED ឬ CANCELLED)
        if (in_array($order->status, ['COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot update status. The order is already {$order->status}."
            ], 400);
        }

        DB::beginTransaction();

        try {
            $newStatus = $request->status;
            $order->status = $newStatus;

            // ==========================================
            // 🌟 ករណីទី ១៖ ជោគជ័យ (COMPLETED) -> អាប់ដេតការបង់ប្រាក់
            // ==========================================
            if ($newStatus === 'COMPLETED') {
                $order->payment_status = 'PAID';

                if ($order->payment) {
                    $order->payment->update([
                        'status'  => 'COMPLETED',
                        'paid_at' => now() // កត់ត្រាថ្ងៃម៉ោងដែលទទួលបានលុយ
                    ]);
                }
            }

            // ==========================================
            // 🌟 ករណីទី ២៖ បោះបង់ (CANCELLED) -> បូកស្តុកទំនិញចូលឃ្លាំងវិញ
            // ==========================================
            if ($newStatus === 'CANCELLED') {
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);

                    if ($product) {
                        // បង្កើត Record បញ្ចូលស្តុក (Stock IN) ទៅក្នុង ProductStockMovement
                        ProductStockMovement::create([
                            'product_id'       => $product->id,
                            'reference_number' => $order->order_number,
                            'type'             => 'IN', // ប្រភេទនាំចូល
                            'quantity'         => $item->quantity,
                            'cost_price'       => $product->cost_price ?? 0,
                            'balance_after'    => $product->current_stock + $item->quantity, // បូកស្តុកបញ្ច្រាសមកវិញ
                            'note'             => 'Restock from cancelled order',
                        ]);
                    }
                }
            }

            $order->save();

            DB::commit();

            if ($order->user) { // បញ្ជាក់ថាភ្ញៀវនេះមានគណនី (មិនមែន Guest)
                $order->user->notify(new OrderStatusUpdatedNotification($order, 'status'));
            }

            return response()->json([
                'success' => true,
                'message' => "Order status successfully updated to {$newStatus}.",
                'data'    => new AdminOrderResource($order)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updatePaymentStatus(Request $request, $id)
    {
        $request->validate([
            'payment_status' => 'required|in:PAID,UNPAID'
        ]);

        $order = Order::with('payment')->findOrFail($id);
        $newStatus = $request->payment_status;

        DB::beginTransaction();
        try {
            $order->payment_status = $newStatus;

            // Update ក្នុង Table Payment ផងដែរ ប្រសិនបើមាន
            if ($order->payment) {
                $order->payment->update([
                    'status'  => $newStatus === 'PAID' ? 'COMPLETED' : 'PENDING',
                    'paid_at' => $newStatus === 'PAID' ? now() : null
                ]);
            }

            $order->save();
            DB::commit();

            if ($order->user) {
                $order->user->notify(new OrderStatusUpdatedNotification($order, 'payment'));
            }

            return response()->json([
                'success' => true,
                'message' => "Payment status updated to {$newStatus}.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function searchProducts(Request $request)
    {
        $search = $request->query('query');

        // Subquery សម្រាប់គណនាស្តុកបច្ចុប្បន្ន (ដូចដែលយើងបានប្រើក្នុង Dashboard)
        $stockSubquery = ProductStockMovement::selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0)")
            ->whereColumn('product_stock_movements.product_id', 'products.id');

        $products = Product::select('id', 'name', 'sku', 'price') // ទាញយកតែ Field ដែលចាំបាច់
            ->selectSub($stockSubquery, 'current_stock') // ភ្ជាប់ចំនួនស្តុកបច្ចុប្បន្ន
            ->where('is_active', true) // ទាញយកតែទំនិញដែលកំពុងលក់
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->with('thumbnail') // ភ្ជាប់រូបភាពមកជាមួយ
            ->take(10) // យកត្រឹម ១០ មុខបានហើយ កុំឱ្យធ្ងន់ប្រព័ន្ធ
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

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

    public function storeManualOrder(Request $request)
    {
        // ជំហានទី ១៖ Validation ពិនិត្យមើលភាពត្រឹមត្រូវនៃទិន្នន័យ
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'shipping_name' => 'required_without:user_id|string|max:255', // បើអត់ user_id ត្រូវតែមានឈ្មោះ
            'shipping_phone' => 'nullable|string|max:20',
            'shipping_address' => 'nullable|string',

            'subtotal' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'shipping_fee' => 'nullable|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',

            'payment_method' => 'required|string',
            'payment_status' => 'required|in:UNPAID,PAID',
            'status' => 'required|in:PENDING,PROCESSING,COMPLETED,CANCELLED',
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1', // ត្រូវតែមានទំនិញយ៉ាងហោច ១
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            // ចាប់ផ្តើម Database Transaction 
            DB::beginTransaction();

            // ជំហានទី ២៖ បង្កើតវិក្កយបត្រថ្មី (Order)
            // បង្កើតលេខកូដ Order ស្វ័យប្រវត្តិ ឧទាហរណ៍៖ ORD-20260519-ABCDE
            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5));

            // ប្រសិនបើអត់មាន user_id (Guest) យើងយកឈ្មោះដែលគេវាយបញ្ចូល
            // តែបើមាន user_id យើងអាចទាញឈ្មោះពី User មកប្រើបាន (បើ shipping_name ទំនេរ)
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
                'notes' => $request->notes,
            ]);

            // ជំហានទី ៣៖ បញ្ចូលទំនិញ (Order Items) និងកាត់ស្តុក (Stock Move)
            foreach ($request->items as $item) {
                // ក. ឆែកមើលស្តុកពិតប្រាកដមុននឹងលក់ (ដើម្បីការពារការលក់លើសស្តុក)
                $currentStock = ProductStockMovement::where('product_id', $item['product_id'])
                    ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0) as total")
                    ->value('total');

                if ($currentStock < $item['quantity']) {
                    // បើស្តុកមិនគ្រប់ បោះ Error ហើយ Transaction នឹងត្រូវលុបចោលទាំងស្រុងដោយស្វ័យប្រវត្តិ
                    throw new \Exception("Product ID {$item['product_id']} does not have enough stock. (Available: {$currentStock})");
                }

                // ខ. បង្កើត Order Item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                ]);

                // គ. កាត់ស្តុកចេញ (OUT)
                ProductStockMovement::create([
                    'product_id' => $item['product_id'],
                    'type' => 'OUT',
                    'quantity' => $item['quantity'],
                    'reference_type' => 'ORDER', // ប្រាប់ថាការកាត់ស្តុកនេះមកពីបញ្ជាទិញ
                    'reference_id' => $order->id,
                    'notes' => 'Manual Order Created: ' . $orderNumber,
                ]);
            }

            // ជំហានទី ៤៖ បញ្ជាក់ការរក្សាទុក (Commit Transaction) 
            // បើកូដរត់មកដល់ត្រង់នេះមានន័យថាគ្មាន Error ទេ
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully.',
                'data' => $order->load('orderItems.product') // បោះទិន្នន័យដែលទើបបង្កើតរួចត្រឡប់ទៅវិញ
            ], 201); 

        } catch (\Exception $e) {
            // ប្រសិនបើមាន Error (ឧ. ស្តុកមិនគ្រប់ ឬ DB មានបញ្ហា) វានឹង Rollback ត្រឡប់ក្រោយវិញ
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 422);
        }
    }
}
