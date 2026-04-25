<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * 🌟 ១. ទាញយកប្រវត្តិការបញ្ជាទិញរបស់អតិថិជន (My Orders)
     */
    public function index(Request $request)
    {
        // ប្រើ $request->user()->id ដើម្បីទាញយកតែ Order របស់ User ដែលកំពុង Login ប៉ុណ្ណោះ
        $orders = Order::with(['items'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * 🌟 ២. មើលលម្អិត Order ណាមួយរបស់ខ្លួនឯង (Order Detail)
     */
    public function show(Request $request, $id)
    {
        // ត្រូវប្រាកដថា Order នោះជារបស់ User នេះមែន
        $order = Order::with(['items'])
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found or unauthorized'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * 🌟 ៣. អតិថិជនធ្វើការបញ្ជាទិញ (Checkout Process)
     */
    public function store(Request $request)
    {
        // ១. Validation ទិន្នន័យ (យើងមិនចាំបាច់សុំ user_id ទេ ព្រោះយើងយកតាម Token របស់គាត់)
        $request->validate([
            'address_id' => 'nullable|uuid',
            'subtotal' => 'required|numeric|min:0',
            'shipping_fee' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'discount_total' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'note' => 'nullable|string',

            // Validation សម្រាប់ Items ខាងក្នុង (Array)
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_name' => 'required|string',
            'items.*.product_sku' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // បង្កើតលេខ Order ស្វ័យប្រវត្តិ
            $orderNumber = 'ORD-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            // ២. បង្កើត Order មេ ដោយប្រើ ID របស់ User ដែលកំពុង Login
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $request->user()->id, // 🌟 យក ID ពី Token សុវត្ថិភាពខ្ពស់
                'address_id' => $request->address_id,
                'subtotal' => $request->subtotal,
                'discount_total' => $request->discount_total,
                'shipping_fee' => $request->shipping_fee,
                'tax_amount' => $request->tax_amount,
                'grand_total' => $request->grand_total,
                'status' => 'PENDING',
                'payment_status' => 'UNPAID',
                'payment_method' => $request->payment_method,
                'note' => $request->note,
            ]);

            // ៣. បញ្ចូលទំនិញទៅក្នុង Order Items
            $orderItems = [];
            foreach ($request->items as $item) {
                $orderItems[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'product_sku' => $item['product_sku'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            OrderItem::insert($orderItems);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('E-commerce Order Failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete checkout. Please try again later.'
            ], 500);
        }
    }
}
