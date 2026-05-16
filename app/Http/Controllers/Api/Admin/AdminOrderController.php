<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStockMovement;
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
}
