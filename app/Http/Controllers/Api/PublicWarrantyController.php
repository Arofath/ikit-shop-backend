<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductSerial;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicWarrantyController extends Controller
{
    public function check(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string'
        ]);

        $sn = trim($request->serial_number);

        $serial = ProductSerial::with([
            'product.warranty',
            'soldMovement'
        ])->where('serial_number', $sn)->first();

        // 1. Serial number not found
        if (!$serial) {
            return response()->json([
                'success' => false,
                'message' => 'Serial number not found in the system.',
                'status'  => 'INVALID'
            ], 404);
        }

        // 2. Product not sold yet
        if ($serial->status !== 'SOLD') {
            return response()->json([
                'success' => true,
                'message' => 'This product has not been sold yet.',
                'status'  => 'AVAILABLE'
            ], 200);
        }

        $soldMovement = $serial->soldMovement;
        if (!$soldMovement) {
            return response()->json([
                'success' => false,
                'message' => 'No order information found for this serial number.',
                'status'  => 'NO_INFO'
            ], 404);
        }

        $orderNumber = $soldMovement->reference_number;
        $order = Order::where('order_number', $orderNumber)->first();

        // 3. Check if warranty exists
        if (!$serial->product->warranty) {
            return response()->json([
                'success' => true,
                'message' => 'This product has no warranty.',
                'status'  => 'NO_WARRANTY',
                'data'    => [
                    'product_name'  => $serial->product->name,
                    'serial_number' => $serial->serial_number,
                    'purchase_date' => $order->created_at->format('Y-m-d'),
                ]
            ], 200);
        }

        // 4. Calculate warranty expiry
        $expiryDate = $serial->warranty_expiry_date;
        $status = $serial->warranty_status;

        return response()->json([
            'success' => true,
            'status'  => strtoupper($status),
            'data'    => [
                'product_name'    => $serial->product->name,
                'serial_number'   => $serial->serial_number,
                'duration_months' => $serial->product->warranty->duration_months ?? 0,
                'purchase_date'   => $soldMovement->created_at->format('Y-m-d H:i:s'),
                'expiry_date'     => $expiryDate ? $expiryDate->format('Y-m-d H:i:s') : null,
                'days_remaining'  => ($status === 'Active' && $expiryDate) ? Carbon::now()->startOfDay()->diffInDays($expiryDate->startOfDay()) : 0
            ]
        ], 200);
    }
}
