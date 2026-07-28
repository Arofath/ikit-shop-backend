<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductSerial;
use Carbon\Carbon;

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
            'movementOut.order'
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

        $order = $serial->movementOut->order ?? null;
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'No order information found for this serial number.',
                'status'  => 'NO_INFO'
            ], 404);
        }

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
        $purchaseDate = $order->created_at;
        $warrantyMonths = $serial->product->warranty->duration_months;
        $expiryDate = $purchaseDate->copy()->addMonths($warrantyMonths);
        $isWarrantyActive = Carbon::now()->lessThanOrEqualTo($expiryDate);

        return response()->json([
            'success' => true,
            'status'  => $isWarrantyActive ? 'ACTIVE' : 'EXPIRED',
            'data'    => [
                'product_name'    => $serial->product->name,
                'serial_number'   => $serial->serial_number,
                'duration_months' => $warrantyMonths,
                'purchase_date'   => $purchaseDate->format('Y-m-d H:i:s'),
                'expiry_date'     => $expiryDate->format('Y-m-d H:i:s'),
                'days_remaining'  => $isWarrantyActive ? Carbon::now()->diffInDays($expiryDate) : 0
            ]
        ], 200);
    }
}
