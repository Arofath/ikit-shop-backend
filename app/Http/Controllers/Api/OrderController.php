<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * មុខងារបញ្ជាទិញ (Checkout)
     */
    public function store(Request $request)
    {
        // ១. ត្រួតពិនិត្យទិន្នន័យដែល Frontend បោះមក
        $request->validate([
            'shipping_name'    => 'required|string|max:255',
            'shipping_phone'   => 'required|string|max:20',
            'shipping_address' => 'required|string',
            'payment_method'   => 'required|in:CASH_ON_DELIVERY,BANK_TRANSFER',
        ]);

        $user = $request->user();

        // ២. ទាញយកកន្ត្រកទំនិញរបស់គាត់ពី Database
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Your cart is empty.'
            ], 400);
        }

        // 🌟 ចាប់ផ្តើម Transaction (បើមាន Error ត្រង់ណា វាលុបចោលវិញទាំងអស់)
        DB::beginTransaction();

        try {
            $subtotal = 0;
            $orderItemsData = [];

            // ៣. ឆែកស្តុក និងគណនាលុយ (Loop តាមទំនិញក្នុងកន្ត្រក)
            foreach ($cart->items as $cartItem) {
                $product = $cartItem->product;

                // ឆែកមើលក្រែងលោមានគេទិញអស់មុន
                if ($product->current_stock < $cartItem->quantity) {
                    throw new \Exception("Product '{$product->name}' is out of stock or insufficient quantity.");
                }

                // គណនាតម្លៃ (យក Final Price ក្រោយបញ្ចុះតម្លៃ)
                $unitPrice = $product->price - ($product->price * ($product->discount_percent / 100));
                $itemSubtotal = $unitPrice * $cartItem->quantity;

                $subtotal += $itemSubtotal;

                // រៀបចំទិន្នន័យសម្រាប់ Save ចូល OrderItem
                $orderItemsData[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'product_sku'  => $product->sku,
                    'quantity'     => $cartItem->quantity,
                    'unit_price'   => $unitPrice,
                    'subtotal'     => $itemSubtotal,
                ];

                // 🌟 កាត់ស្តុកទំនិញចេញពីឃ្លាំង
                $product->decrement('current_stock', $cartItem->quantity);
            }

            // ៤. គណនាតម្លៃចុងក្រោយ
            $shippingFee = 0; // អាចកំណត់ថ្លៃដឹកជញ្ជូននៅទីនេះ (ឧទាហរណ៍៖ 2.00)
            $grandTotal = $subtotal + $shippingFee;

            // ៥. បង្កើតវិក្កយបត្រមេ (Order)
            $order = Order::create([
                'order_number'     => 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                'user_id'          => $user->id,
                'shipping_name'    => $request->shipping_name,
                'shipping_phone'   => $request->shipping_phone,
                'shipping_address' => $request->shipping_address,
                'subtotal'         => $subtotal,
                'shipping_fee'     => $shippingFee,
                'grand_total'      => $grandTotal,
                'status'           => 'PENDING',
                'payment_status'   => 'UNPAID',
                'payment_method'   => $request->payment_method,
            ]);

            // ៦. បង្កើតទំនិញក្នុងវិក្កយបត្រ (Order Items)
            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            // ៧. បង្កើតប្រតិបត្តិការបង់ប្រាក់ (Payment)
            $order->payment()->create([
                'amount'         => $grandTotal,
                'payment_method' => $request->payment_method,
                'status'         => 'PENDING',
            ]);

            // ៨. សម្អាតកន្ត្រកទំនិញ (លុបចោលព្រោះទិញរួចហើយ)
            $cart->items()->delete();

            // 🌟 បញ្ចប់ Transaction ដោយជោគជ័យ
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully.',
                'order_id' => $order->id, // បោះ ID អោយ Frontend ដើម្បីលោតទៅ Thank You Page
                'order_number' => $order->order_number
            ], 201);
        } catch (\Exception $e) {
            // 🚨 បើមានបញ្ហា (ឧ. អស់ស្តុក) លុបចោលប្រតិបត្តិការទាំងអស់ខាងលើ
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Checkout failed: ' . $e->getMessage()
            ], 400);
        }
    }

    public function index(Request $request)
    {
        // ទាញយកតែ Order ណាដែលជារបស់ User ដែលកំពុង Login ប៉ុណ្ណោះ
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product.thumbnail', 'payment']) // ទាញយកទំនិញ រូបភាព និងការបង់ប្រាក់
            ->latest() // យកវិក្កយបត្រថ្មីៗមកបង្ហាញមុនគេ
            ->paginate(10); // បែងចែក ១០ វិក្កយបត្រក្នុង ១ ទំព័រ

        return response()->json([
            'success' => true,
            'message' => 'Order history fetched successfully.',
            'data'    => OrderResource::collection($orders)->response()->getData(true)
        ], 200);
    }

    /**
     * មុខងារមើលព័ត៌មានលម្អិតនៃវិក្កយបត្រណាមួយ (Order Detail)
     */
    public function show(Request $request, string $id)
    {
        // 🌟 សុវត្ថិភាព៖ ស្វែងរក Order តាម ID និងត្រូវតែជារបស់ User នេះផ្ទាល់
        $order = Order::with(['items.product.thumbnail', 'payment'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        // បើរកមិនឃើញ ឬមិនមែនជារបស់គាត់
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or unauthorized.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order details fetched successfully.',
            'data'    => new OrderResource($order)
        ], 200);
    }
}
