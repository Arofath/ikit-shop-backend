<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Models\ProductSerial;
use App\Models\ProductStockMovement;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\ReceiptRejectedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Admin Order
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
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                    ->orWhere('shipping_name', 'LIKE', "%{$search}%");
            });
        }

        // 🌟 មុខងារថ្មីសម្រាប់ Drill-down Date Filter
        if ($request->has('date_filter') && $request->date_filter != '') {
            $range = $request->date_filter;
            // ហៅ Private Method ខាងក្រោមមកប្រើដើម្បីបំប្លែងពាក្យទៅជាកាលបរិច្ឆេទ
            [$start, $end] = $this->getDatesFromRange($range);

            // Filter តាមថ្ងៃខែដែលបង្កើតវិក្កយបត្រ
            $query->whereBetween('created_at', [$start, $end]);
        }

        $orders = $query->paginate(15);
        $resource = AdminOrderResource::collection($orders)->response()->getData(true);

        return response()->json([
            'success' => true,
            'data'    => $resource['data'],
            'meta'    => $resource['meta']
        ]);
    }

    private function getDatesFromRange($range)
    {
        $now = \Carbon\Carbon::now();

        switch ($range) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'yesterday':
                return [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()];
            case 'last_7_days':
                return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()];
            case 'last_month':
                return [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()];
            case 'this_year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfDay()];
            case 'this_month':
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfDay()];
        }
    }

    /**
     * ២. មើលព័ត៌មានលម្អិតនៃវិក្កយបត្រណាមួយ
     */
    public function show($id)
    {
        // 🌟 ថែម 'statusUpdater' និង 'paymentProcessor' ចូលទៅក្នុង with()
        $order = Order::with([
            'user',
            'items.product.thumbnail',
            'items.product.serials',
            'payment',
            'statusUpdater',     // ទាញយកអ្នកប្តូរ Order Status
            'paymentProcessor'   // ទាញយកអ្នកប្តូរ Payment Status
        ])->findOrFail($id);

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

        $order = Order::with(['items.product', 'payment'])->findOrFail($id);

        if (in_array($order->status, ['COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot update status. The order is already {$order->status}."
            ], 400);
        }

        DB::beginTransaction();

        try {
            $newStatus = $request->status;

            if ($newStatus === 'COMPLETED') {
                foreach ($order->items as $item) {
                    $product = $item->product;

                    if ($product && $product->is_serialized) {
                        $outMovement = ProductStockMovement::where('reference_number', $order->order_number)
                            ->where('product_id', $product->id)
                            ->where('type', 'OUT')
                            ->first();

                        if ($outMovement) {
                            $scannedCount = ProductSerial::where('sold_movement_id', $outMovement->id)->count();

                            if ($scannedCount < $outMovement->quantity) {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => "Cannot change to COMPLETED. Product '{$product->name}' requires serials but only {$scannedCount}/{$outMovement->quantity} have been scanned."
                                ], 400);
                            }
                        }
                    }
                }
            }

            $order->status = $newStatus;
            $order->status_updated_by = $request->user()->id; // 🌟 កត់ត្រាអ្នកប្តូរ Status

            if ($newStatus === 'COMPLETED') {
                $order->payment_status = 'PAID';
                // បើប្តូរទៅជា PAID ដោយស្វ័យប្រវត្តិ គួរតែកត់ត្រាអ្នកប្តូរ Payment ដែរ
                $order->payment_processed_by = $request->user()->id;

                if ($order->payment) {
                    $order->payment->update([
                        'status'  => 'COMPLETED',
                        'paid_at' => now()
                    ]);
                }
            }

            if ($newStatus === 'CANCELLED') {
                foreach ($order->items as $item) {
                    $product = $item->product;

                    if ($product) {
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

                        \App\Models\ProductStockMovement::create([
                            'product_id'       => $product->id,
                            'reference_number' => $order->order_number,
                            'type'             => 'IN',
                            'quantity'         => $item->quantity,
                            'cost_price'       => $product->cost_price ?? 0,
                            'balance_after'    => $product->current_stock + $item->quantity,
                            'note'             => 'Restock from cancelled order',
                        ]);
                    }
                }
            }

            $order->save();

            DB::commit();

            if ($order->user) {
                $order->user->notify(new OrderStatusUpdatedNotification($order, 'status'));
            }

            // 🌟 Reload Relationship មុនបោះទៅ Frontend ដើម្បីឱ្យ UI ស្គាល់ឈ្មោះអ្នក Update ភ្លាមៗ
            $order->load(['statusUpdater', 'paymentProcessor']);

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
            $order->payment_processed_by = $request->user()->id; // 🌟 កត់ត្រាអ្នកប្តូរ Payment

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

            // 🌟 Reload Relationship មុនបោះទៅ Frontend ដើម្បីឱ្យ UI ស្គាល់ឈ្មោះអ្នក Update ភ្លាមៗ
            $order->load(['statusUpdater', 'paymentProcessor']);

            return response()->json([
                'success' => true,
                'message' => "Payment status updated to {$newStatus}.",
                'data'    => new AdminOrderResource($order) // 🌟 ឥឡូវនេះវាបោះ Data ទៅឱ្យ Frontend វិញហើយ
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

        if (!in_array($order->status, ['CANCELLED', 'COMPLETED'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete order. Only CANCELLED or COMPLETED orders can be deleted. Current status is {$order->status}."
            ], 400);
        }

        try {
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

        if (in_array($order->status, ['COMPLETED', 'CANCELLED'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot scan serials. Order is already {$order->status}."
            ], 400);
        }

        DB::beginTransaction();

        try {
            $serial = ProductSerial::where('serial_number', $request->serial_number)
                ->lockForUpdate()
                ->first();

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

            $orderItem = $order->items->where('product_id', $serial->product_id)->first();

            if (!$orderItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mismatch Error: This serial number belongs to a product that is NOT in this order.'
                ], 400);
            }

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

            $scannedCount = ProductSerial::where('sold_movement_id', $outMovement->id)->count();

            if ($scannedCount >= $outMovement->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Fulfilled: All {$outMovement->quantity} serial(s) for this product have already been scanned."
                ], 400);
            }

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
                    'scanned_count'  => $scannedCount + 1,
                    'required_count' => $outMovement->quantity,
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

    /**
     * មុខងារបដិសេធវិក្កយបត្របង់ប្រាក់ (Reject Receipt)
     */
    public function rejectPaymentReceipt(Request $request, $id)
    {
        $request->validate([
            'payment_note' => 'required|string|max:1000'
        ]);

        $order = Order::findOrFail($id);

        if ($order->payment_status === 'PAID') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reject receipt. Order is already marked as PAID.'
            ], 400);
        }

        $order->payment_status = 'INVALID_RECEIPT';
        $order->payment_note = $request->payment_note;
        $order->save();

        if ($order->user) {
            $order->user->notify(new ReceiptRejectedNotification($order, $request->payment_note));
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment receipt has been rejected.',
        ]);
    }
}
