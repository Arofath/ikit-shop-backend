<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\ShippingZone;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Notifications\TelegramOrderNotification;
use App\Services\CloudinaryStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * មុខងារបញ្ជាទិញ (Checkout)
     */
    public function store(Request $request)
    {
        // 🌟 ១. ផ្លាស់ប្តូរ Validation ពី 'city' ទៅជា 'shipping_zone_id'
        $request->validate([
            'shipping_name'    => 'required|string|max:255',
            'shipping_phone'   => 'required|string|max:20',
            'shipping_zone_id' => 'required|exists:shipping_zones,id',
            'shipping_address' => 'required|string',
            'payment_method'   => 'required|in:CASH_ON_DELIVERY,BANK_TRANSFER',
        ]);

        // 🌟 ២. ស្វែងរក Zone ដើម្បីឆែកមើលលក្ខខណ្ឌ COD
        $shippingZone = ShippingZone::find($request->shipping_zone_id);

        // ការពារការកម្ម៉ង់ COD បើទិសដៅមិនមែនភ្នំពេញ (ប្រើឈ្មោះ Zone សម្រាប់ប្រៀបធៀប)
        if ($request->payment_method === 'CASH_ON_DELIVERY' && strtolower(trim($shippingZone->name)) !== 'phnom penh') {
            return response()->json([
                'success' => false,
                'message' => 'Cash on Delivery (COD) is only available in Phnom Penh.'
            ], 400);
        }

        $user = $request->user();
        $cart = Cart::with('items.product')->where('user_id', $user->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Your cart is empty.'], 400);
        }

        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(5));

        DB::beginTransaction();

        try {
            // ក. រៀបចំទំនិញ ទាញតម្លៃ Surcharge និងកាត់ស្តុក
            $processedData = $this->processCartItems($cart->items, $orderNumber);

            // 🌟 ខ. គណនាថ្លៃដឹកជញ្ជូនតាមរូបមន្ត ៣ ជំហាន
            $shippingData = $this->calculateShippingFee(
                $shippingZone,
                $processedData['final_subtotal'], // យកតម្លៃ Subtotal ក្រោយចុះថ្លៃ
                $processedData['bulky_surcharge_total']
            );

            // គណនាតម្លៃសរុបចុងក្រោយ (Grand Total)
            $grandTotal = $processedData['final_subtotal'] + $shippingData['total_shipping_fee'];

            // គ. បង្កើតវិក្កយបត្រមេ
            $order = $this->createOrderRecord(
                $user,
                $request,
                $orderNumber,
                $processedData['subtotal'],       // តម្លៃដើម
                $processedData['discount_total'], // ទំហំលុយចុះថ្លៃសរុប
                $shippingData,                    // ទិន្នន័យដឹកជញ្ជូនទាំង ៣ Column
                $grandTotal
            );

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

            $order->load(['items', 'payment', 'shippingZone']);

            $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewOrderNotification($order));
            }

            Notification::route('telegram', env('TELEGRAM_CHAT_ID'))
                ->notify(new TelegramOrderNotification($order));

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

    public function updateOrderAddress(Request $request, $id)
    {
        // ១. ស្វែងរកវិក្កយបត្ររបស់ User នោះ
        $order = Order::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        // ២. លក្ខខណ្ឌទី ១៖ បើអីវ៉ាន់ចេញដឹក ឬដល់ដៃភ្ញៀវហើយ គឺមិនអនុញ្ញាតឱ្យកែទេ
        if (in_array($order->status, ['SHIPPING', 'COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order is already processed or shipped. Cannot update address.'
            ], 400);
        }

        // ៣. ផ្ទៀងផ្ទាត់ទិន្នន័យដែលបញ្ជូនមក
        $validated = $request->validate([
            'shipping_name'    => 'required|string|max:255',
            'shipping_phone'   => 'required|string|max:20',
            'shipping_address' => 'required|string', // នេះគឺជា address_detail
            'shipping_zone_id' => 'required|exists:shipping_zones,id',
        ]);

        // ៤. លក្ខខណ្ឌទី ២៖ បើបង់លុយរួច (PAID) ហាមដូរខេត្តដាច់ខាត!
        if ($order->payment_status === 'PAID' && $order->shipping_zone_id !== $validated['shipping_zone_id']) {
            return response()->json([
                'success' => false,
                'message' => 'This order is already PAID. You cannot change the shipping province/city. Please contact support.'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // អាប់ដេតព័ត៌មានទូទៅ
            $order->shipping_name    = $validated['shipping_name'];
            $order->shipping_phone   = $validated['shipping_phone'];
            $order->shipping_address = $validated['shipping_address'];

            // ៥. លក្ខខណ្ឌទី ៣៖ បើ UNPAID ហើយគាត់ដូរខេត្ត យើងត្រូវគណនាលុយឡើងវិញ
            if ($order->payment_status !== 'PAID' && $order->shipping_zone_id !== $validated['shipping_zone_id']) {

                $newZone = DB::table('shipping_zones')->where('id', $validated['shipping_zone_id'])->first();
                $order->shipping_zone_id = $newZone->id;

                // រូបមន្តគណនាថ្លៃដឹកជញ្ជូន
                $baseCost = $newZone->base_cost;

                // ឆែកមើលលក្ខខណ្ឌ Free Shipping Threshold
                if (!is_null($newZone->free_shipping_threshold) && $order->subtotal >= $newZone->free_shipping_threshold) {
                    $baseCost = 0;
                }

                // អាប់ដេតតម្លៃថ្មីចូល Order
                $order->base_shipping_cost = $baseCost;
                $order->shipping_fee = $baseCost + $order->bulky_surcharge_total;

                // គណនា Grand Total ថ្មី = Subtotal + Shipping - Discount (បើមាន)
                $discount = $order->discount_amount ?? 0;
                $order->grand_total = $order->subtotal + $order->shipping_fee - $discount;
            }

            $order->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order shipping address updated successfully.',
                'data'    => clone $order // បោះទិន្នន័យថ្មីទៅឱ្យ Frontend
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }


    // =====================================================================
    // 🌟 មុខងារថ្មីសម្រាប់គណនាថ្លៃដឹកជញ្ជូន (Core Logic)
    // =====================================================================
    private function calculateShippingFee($shippingZone, $finalSubtotal, $bulkySurchargeTotal)
    {
        $baseShippingCost = (float) $shippingZone->base_cost;

        // ជំហានទី ១៖ ផ្ទៀងផ្ទាត់លក្ខខណ្ឌ Free Shipping
        // បើមានកំណត់ threshold ហើយភ្ញៀវទិញលើស ឬស្មើ នោះ Base Cost នឹងក្លាយជា 0 
        if ($shippingZone->free_shipping_threshold !== null) {
            if ($finalSubtotal >= (float) $shippingZone->free_shipping_threshold) {
                $baseShippingCost = 0.00;
            }
        }

        // ជំហានទី ២៖ Bulky Surcharge ត្រូវបានគណនារួចហើយក្នុង processCartItems()

        // ជំហានទី ៣៖ សរុបថ្លៃដឹកជញ្ជូនចុងក្រោយ
        $totalShippingFee = $baseShippingCost + $bulkySurchargeTotal;

        return [
            'base_shipping_cost'    => $baseShippingCost,
            'bulky_surcharge_total' => $bulkySurchargeTotal,
            'total_shipping_fee'    => $totalShippingFee
        ];
    }

    // ឆែកស្តុក កាត់ស្តុក និងរៀបចំទិន្នន័យទំនិញ
    private function processCartItems($cartItems, $orderNumber)
    {
        $originalSubtotal = 0;
        $totalDiscountAmount = 0;
        $bulkySurchargeTotal = 0; // 🌟 បន្ថែមអថេរសម្រាប់បូកសរុប Surcharge
        $orderItemsData = [];

        foreach ($cartItems as $cartItem) {
            $product = Product::lockForUpdate()->find($cartItem->product_id);

            if (!$product || $product->current_stock < $cartItem->quantity) {
                throw new \Exception("Sorry, Product '{$cartItem->product->name}' is out of stock or insufficient quantity.");
            }

            // គណនាតម្លៃបញ្ចុះតម្លៃក្នុងមួយឯកតា
            $discountPerUnit = $product->price * (($product->discount_percent ?? 0) / 100);
            $unitPrice = $product->price - $discountPerUnit; // តម្លៃលក់ចេញពិតប្រាកដ

            // គណនាសរុបតាម Item
            $itemOriginalSubtotal = $product->price * $cartItem->quantity;
            $itemDiscountTotal = $discountPerUnit * $cartItem->quantity;
            $itemFinalSubtotal = $unitPrice * $cartItem->quantity;

            // 🌟 គណនា Surcharge សម្រាប់ទំនិញនេះ
            $itemSurcharge = ($product->shipping_surcharge ?? 0) * $cartItem->quantity;

            $originalSubtotal += $itemOriginalSubtotal;
            $totalDiscountAmount += $itemDiscountTotal;
            $bulkySurchargeTotal += $itemSurcharge; // បូកបញ្ចូលទៅសរុប

            $orderItemsData[] = [
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'product_sku'  => $product->sku,
                'quantity'     => $cartItem->quantity,
                'unit_price'   => $unitPrice,
                'subtotal'     => $itemFinalSubtotal,
            ];

            ProductStockMovement::create([
                'product_id'       => $product->id,
                'reference_number' => $orderNumber,
                'type'             => 'OUT',
                'quantity'         => $cartItem->quantity,
                'cost_price'       => $product->cost_price ?? 0,
                'balance_after'    => $product->current_stock - $cartItem->quantity,
                'note'             => 'Reserved for Order (Pending Fulfillment): ' . $orderNumber,
            ]);
        }

        return [
            'subtotal'              => $originalSubtotal,
            'discount_total'        => $totalDiscountAmount,
            'final_subtotal'        => $originalSubtotal - $totalDiscountAmount, // 🌟 តម្លៃក្រោយកាត់ខាត
            'bulky_surcharge_total' => $bulkySurchargeTotal, // 🌟 បោះ Surcharge សរុបទៅក្រៅ
            'items_data'            => $orderItemsData
        ];
    }

    // បង្កើតវិក្កយបត្រ (Order Model)
    private function createOrderRecord($user, $request, $orderNumber, $subtotal, $discountTotal, $shippingData, $grandTotal)
    {
        return Order::create([
            'order_number'          => $orderNumber,
            'user_id'               => $user->id,
            'shipping_name'         => $request->shipping_name,
            'shipping_phone'        => $request->shipping_phone,
            'shipping_address'      => $request->shipping_address,
            'shipping_zone_id'      => $request->shipping_zone_id, // 🌟 បញ្ចូល Zone ID
            'subtotal'              => $subtotal,
            'discount_total'        => $discountTotal,
            'base_shipping_cost'    => $shippingData['base_shipping_cost'],    // 🌟 បញ្ចូល Base
            'bulky_surcharge_total' => $shippingData['bulky_surcharge_total'], // 🌟 បញ្ចូល Surcharge
            'shipping_fee'          => $shippingData['total_shipping_fee'],    // 🌟 បញ្ចូល Total Fee
            'grand_total'           => $grandTotal,
            'status'                => 'PENDING',
            'payment_status'        => 'UNPAID',
            'payment_method'        => $request->payment_method,
        ]);
    }

    // ... (កូដ index, show, និង uploadReceipt រក្សាទុកនៅដដែល) ...

    public function index(Request $request)
    {
        $status = $request->query('status');

        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items.product.thumbnail', 'payment', 'shippingZone']) // 🌟 Load ShippingZone

            ->when($status && strtoupper($status) !== 'ALL', function ($query) use ($status) {
                return $query->where('status', strtoupper($status));
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Order history fetched successfully.',
            'data'    => OrderResource::collection($orders)->response()->getData(true)
        ], 200);
    }

    public function show(Request $request, string $id)
    {
        $order = Order::with(['items.product.thumbnail', 'payment', 'shippingZone']) // 🌟 Load ShippingZone
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

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

    public function uploadReceipt(Request $request, string $id, CloudinaryStorageService $cloudinaryService)
    {
        $request->validate([
            'receipt' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $order = Order::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or unauthorized.'
            ], 404);
        }

        if ($order->payment_method !== 'BANK_TRANSFER') {
            return response()->json([
                'success' => false,
                'message' => 'Receipt upload is only required for Bank Transfer.'
            ], 400);
        }

        if ($request->hasFile('receipt')) {
            try {
                $secureUrl = $cloudinaryService->uploadImage(
                    $request->file('receipt'),
                    'receipts',
                    $order->payment_receipt
                );

                $order->payment_receipt = $secureUrl;
                $order->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Payment receipt uploaded successfully.',
                    'receipt_url' => $secureUrl
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload receipt to Cloudinary: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'No file uploaded.'
        ], 400);
    }
}
