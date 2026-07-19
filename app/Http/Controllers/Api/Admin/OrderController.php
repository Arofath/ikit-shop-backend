<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Models\ProductSerial;
use App\Models\ProductStockMovement;
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
        // 🌟 ថែម 'items.product.serials' ចូលទៅក្នុង with()
        $order = Order::with(['user', 'items.product.thumbnail', 'items.product.serials', 'payment'])->findOrFail($id);

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

        // 🌟 ត្រូវប្រាកដថាបានទាញយក items.product មកជាមួយ ដើម្បីឆែកមើល is_serialized
        $order = Order::with(['items.product', 'payment'])->findOrFail($id);

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

            // ==========================================
            // 🌟 កូដការពារ៖ ឆែកមើល Serial មុននឹងឱ្យប្តូរទៅ COMPLETED
            // ==========================================
            if ($newStatus === 'COMPLETED') {
                foreach ($order->items as $item) {
                    $product = $item->product;

                    // បើទំនិញនេះត្រូវការ Serial នោះយើងត្រូវឆែកមើល
                    if ($product && $product->is_serialized) {

                        // ១. រកមើលប្រវត្តិដកស្តុក (OUT) របស់ទំនិញនេះ ក្នុងវិក្កយបត្រនេះ
                        $outMovement = ProductStockMovement::where('reference_number', $order->order_number)
                            ->where('product_id', $product->id)
                            ->where('type', 'OUT')
                            ->first();

                        if ($outMovement) {
                            // ២. រាប់ចំនួន Serial ដែល Admin បានស្កេនបញ្ចូល ធៀបនឹងចំនួនដែលបានកម្ម៉ង់
                            $scannedCount = ProductSerial::where('sold_movement_id', $outMovement->id)->count();

                            // ៣. បើស្កេនមិនទាន់គ្រប់ទេ បោះ Error បដិសេធភ្លាមៗ!
                            if ($scannedCount < $outMovement->quantity) {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => "មិនអាចប្តូរទៅ COMPLETED បានទេ! ទំនិញ '{$product->name}' ទាមទារ Serial តែអ្នកទើបតែស្កេនបាន {$scannedCount}/{$outMovement->quantity} ប៉ុណ្ណោះ។"
                                ], 400);
                            }
                        }
                    }
                }
            }

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
            // 🌟 ករណីទី ២៖ បោះបង់ (CANCELLED) -> បូកស្តុកទំនិញចូលឃ្លាំងវិញ និងដក Serial
            // ==========================================
            if ($newStatus === 'CANCELLED') {
                foreach ($order->items as $item) {
                    $product = $item->product; // ប្រើ Relationship ដែលមានស្រាប់

                    if ($product) {
                        // 🌟 បន្ថែម៖ បើមានស្កេន Serial ខ្លះហើយ ពេល Cancel ត្រូវដក Serial នោះចេញពី Order នេះវិញ (ដូរទៅ AVAILABLE វិញ)
                        if ($product->is_serialized) {
                            $outMovement = ProductStockMovement::where('reference_number', $order->order_number)
                                ->where('product_id', $product->id)
                                ->where('type', 'OUT')
                                ->first();

                            if ($outMovement) {
                                \App\Models\ProductSerial::where('sold_movement_id', $outMovement->id)
                                    ->update([
                                        'status' => 'AVAILABLE',
                                        'sold_movement_id' => null
                                    ]);
                            }
                        }

                        // បង្កើត Record បញ្ចូលស្តុក (Stock IN) ទៅក្នុង ProductStockMovement
                        \App\Models\ProductStockMovement::create([
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

    /**
     * ៤. មុខងារលុបវិក្កយបត្រ (Delete Order)
     */
    public function destroy($id)
    {
        $order = Order::findOrFail($id);

        // ត្រួតពិនិត្យ៖ អនុញ្ញាតឱ្យលុបតែ Order ណាដែល CANCELLED ឬ COMPLETED ប៉ុណ្ណោះ
        if (!in_array($order->status, ['CANCELLED', 'COMPLETED'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete order. Only CANCELLED or COMPLETED orders can be deleted. Current status is {$order->status}."
            ], 400);
        }

        try {
            // ដោយសារ Table orders មានប្រើ softDeletes()
            // វានឹងគ្រាន់តែ Update ជួរឈរ deleted_at មិនលុបទិន្នន័យចោលទាំងស្រុងពី Database ទេ
            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Order successfully deleted.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ៥. មុខងារស្កេនបញ្ចូល Serial Number សម្រាប់វិក្កយបត្រ (Standard Flow)
     */
    public function fulfillOrderSerials(Request $request, $id)
    {
        $request->validate([
            'serial_number' => 'required|string',
        ]);

        $order = Order::with('items.product')->findOrFail($id);

        // ការពារកុំឱ្យស្កេនបញ្ចូល Serial លើវិក្កយបត្រដែលបិទបញ្ជីរួច
        if (in_array($order->status, ['COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot scan serials. Order is already {$order->status}."
            ], 400);
        }

        DB::beginTransaction();

        try {
            // ១. ស្វែងរក Serial Number នៅក្នុងប្រព័ន្ធ (ប្រើ lockForUpdate ដើម្បីការពារ Admin ២នាក់ ស្កេន Serial តែមួយជាន់គ្នា)
            $serial = ProductSerial::where('serial_number', $request->serial_number)
                ->lockForUpdate()
                ->first();

            // ករណីទី ២ និងទី ៣ នឹងត្រូវសរសេរចូលត្រង់ចំណុចនេះនៅពេលក្រោយ (Smart Solution ពេលរកមិនឃើញ)
            if (!$serial) {
                return response()->json([
                    'success' => false,
                    'message' => 'Serial number not found in the system.'
                ], 404);
            }

            if ($serial->status !== 'AVAILABLE') {
                return response()->json([
                    'success' => false,
                    'message' => "This serial number is already {$serial->status}."
                ], 400);
            }

            // ២. ផ្ទៀងផ្ទាត់ថាតើ Serial នេះជារបស់ Product ដែលភ្ញៀវបានកម្ម៉ង់មែនឬអត់?
            $orderItem = $order->items->where('product_id', $serial->product_id)->first();

            if (!$orderItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mismatch Error: This serial number belongs to a product that is NOT in this order.'
                ], 400);
            }

            // ៣. ស្វែងរក Movement (OUT) ដែលបានកក់ទុកពេល Checkout ដោយភ្ញៀវ
            $outMovement = ProductStockMovement::where('reference_number', $order->order_number)
                ->where('product_id', $serial->product_id)
                ->where('type', 'OUT')
                ->first();

            if (!$outMovement) {
                return response()->json([
                    'success' => false,
                    'message' => 'System Error: Cannot find the reserved stock movement (OUT) for this product.'
                ], 500);
            }

            // ៤. ឆែកមើលចំនួន Serial ដែលបានស្កេនរួច ធៀបនឹងចំនួនដែលបានកម្ម៉ង់
            $scannedCount = ProductSerial::where('sold_movement_id', $outMovement->id)->count();

            if ($scannedCount >= $outMovement->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Fulfilled: All {$outMovement->quantity} serial(s) for this product have already been scanned."
                ], 400);
            }

            // ៥. អាប់ដេត Serial ទៅជា SOLD និងភ្ជាប់ទៅកាន់ Movement (OUT) នៃវិក្កយបត្រនេះ
            $serial->update([
                'status'           => 'SOLD',
                'sold_movement_id' => $outMovement->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Serial number successfully linked to the order.',
                'data'    => [
                    'product_name'   => $orderItem->product->name,
                    'serial_number'  => $serial->serial_number,
                    'scanned_count'  => $scannedCount + 1, // ចំនួនដែលស្កេនបាន
                    'required_count' => $outMovement->quantity, // ចំនួនសរុបដែលត្រូវស្កេន
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to fulfill serial: ' . $e->getMessage()
            ], 500);
        }
    }
}
