<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductStockMovement;
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
        $request->validate([
            'shipping_name'    => 'required|string|max:255',
            'shipping_phone'   => 'required|string|max:20',
            'city'             => 'required|string',
            'shipping_address' => 'required|string',
            'payment_method'   => 'required|in:CASH_ON_DELIVERY,BANK_TRANSFER',
        ]);

        $user = $request->user();
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Your cart is empty.'], 400);
        }

        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(5));

        DB::beginTransaction();

        try {
            // ហៅមុខងាររងមកធ្វើការបន្តបន្ទាប់គ្នា (Clean & Readable)

            // ក. រៀបចំទំនិញ និងកាត់ស្តុក
            $processedData = $this->processCartItems($cart->items, $orderNumber);

            // ខ. គណនាថ្លៃដឹក និងតម្លៃសរុប
            $shippingFee = $this->calculateShippingFee($request->city);
            $grandTotal  = $processedData['subtotal'] + $shippingFee;

            // គ. បង្កើតវិក្កយបត្រមេ
            $order = $this->createOrderRecord($user, $request, $orderNumber, $processedData['subtotal'], $shippingFee, $grandTotal);

            // ឃ. បញ្ចូលបញ្ជីទំនិញទៅក្នុងវិក្កយបត្រ
            $order->items()->createMany($processedData['items_data']);

            // ង. បង្កើតប្រតិបត្តិការបង់ប្រាក់
            $order->payment()->create([
                'amount'         => $grandTotal,
                'payment_method' => $request->payment_method,
                'status'         => 'PENDING',
            ]);

            // ច. សម្អាតកន្ត្រកទំនិញ
            $cart->items()->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully.',
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Checkout failed: ' . $e->getMessage()
            ], 400);
        }
    }

    // Helper Functions
    private function calculateShippingFee($city)
    {
        $cityName = strtolower(trim($city));
        return ($cityName === 'phnom penh') ? 2.00 : 2.50;
    }

    //ឆែកស្តុក កាត់ស្តុក និងរៀបចំទិន្នន័យទំនិញ
    private function processCartItems($cartItems, $orderNumber)
    {
        $subtotal = 0;
        $orderItemsData = [];

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;

            if ($product->current_stock < $cartItem->quantity) {
                throw new \Exception("Product '{$product->name}' is out of stock or insufficient quantity.");
            }

            $unitPrice = $product->price - ($product->price * ($product->discount_percent / 100));
            $itemSubtotal = $unitPrice * $cartItem->quantity;
            $subtotal += $itemSubtotal;

            $orderItemsData[] = [
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'product_sku'  => $product->sku,
                'quantity'     => $cartItem->quantity,
                'unit_price'   => $unitPrice,
                'subtotal'     => $itemSubtotal,
            ];

            // កត់ត្រាចលនាស្តុក
            ProductStockMovement::create([
                'product_id'       => $product->id,
                'reference_number' => $orderNumber,
                'type'             => 'OUT',
                'quantity'         => $cartItem->quantity,
                'cost_price'       => $product->cost_price ?? 0,
                'balance_after'    => $product->current_stock - $cartItem->quantity,
                'note'             => 'Product sold via checkout',
            ]);
        }

        return [
            'subtotal'   => $subtotal,
            'items_data' => $orderItemsData
        ];
    }

    // បង្កើតវិក្កយបត្រ (Order Model)
    private function createOrderRecord($user, $request, $orderNumber, $subtotal, $shippingFee, $grandTotal)
    {
        return Order::create([
            'order_number'     => $orderNumber,
            'user_id'          => $user->id,
            'shipping_name'    => $request->shipping_name,
            'shipping_phone'   => $request->shipping_phone,
            'shipping_address' => $request->shipping_address, // ទីនេះទុកពេញដដែល
            'subtotal'         => $subtotal,
            'shipping_fee'     => $shippingFee,
            'grand_total'      => $grandTotal,
            'status'           => 'PENDING',
            'payment_status'   => 'UNPAID',
            'payment_method'   => $request->payment_method,
        ]);
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
