<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSerial;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WarrantyCheckController extends Controller
{
    public function check(Request $request)
    {
        // ១. ត្រួតពិនិត្យថាមានបញ្ជូន serial_number មកឬទេ
        $request->validate([
            'serial_number' => 'required|string'
        ]);

        $sn = trim($request->serial_number);

        // ២. ស្វែងរក Serial នៅក្នុង Database ដោយហៅ relation soldMovement តាម Model របស់អ្នក
        $serial = ProductSerial::with([
            'product.warranty',
            'soldMovement'
        ])->where('serial_number', $sn)->first();

        // ករណីទី ១៖ រកមិនឃើញលេខ Serial នេះទាល់តែសោះ
        if (!$serial) {
            return response()->json([
                'success' => false,
                'message' => 'Serial number not found in the system.',
                'status'  => 'INVALID'
            ], 404);
        }

        // ករណីទី ២៖ ទំនិញមិនទាន់លក់ចេញ (នៅក្នងស្តុក)
        if ($serial->status !== 'SOLD') {
            return response()->json([
                'success' => true,
                'message' => 'Product is still in stock and not sold yet.',
                'status'  => 'AVAILABLE',
                'data'    => [
                    'product_name'  => $serial->product->name ?? 'Unknown',
                    'serial_number' => $serial->serial_number,
                ]
            ], 200);
        }

        // ករណីទី ៣៖ ទំនិញលក់ចេញរួច (មាន soldMovement)
        $soldMovement = $serial->soldMovement;

        if (!$soldMovement) {
            return response()->json([
                'success' => false,
                'message' => 'Missing sale record for this serial.',
                'status'  => 'ERROR'
            ], 404);
        }

        // ស្វែងរក Order តាមរយៈ reference_number (ដែលផ្ទុក Order Number)
        $orderNumber = $soldMovement->reference_number;
        $order = Order::where('order_number', $orderNumber)->first();

        // 🌟 ប្រើប្រាស់ Accessors ដែលមានស្រាប់ក្នុង Model ProductSerial របស់អ្នក
        $expiryDate = $serial->warranty_expiry_date;
        $status = $serial->warranty_status; // នឹងចេញ 'Active' ឬ 'Expired'

        return response()->json([
            'success' => true,
            'message' => 'Warranty status checked successfully.',
            'status'  => strtoupper($status),
            'data'    => [
                'serial_number'   => $serial->serial_number,
                'product_name'    => $serial->product->name,
                'duration_months' => $serial->product->warranty->duration_months ?? 0,
                'purchase_date'   => $soldMovement->created_at->format('Y-m-d H:i:s'),
                'expiry_date'     => $expiryDate ? $expiryDate->format('Y-m-d H:i:s') : null,
                'customer_name'   => $order ? ($order->shipping_name ?? $order->user?->name ?? 'Walk-in Customer') : 'Unknown',
                'order_number'    => $orderNumber ?? 'N/A',
                'days_remaining'  => ($status === 'Active' && $expiryDate) ? Carbon::now()->startOfDay()->diffInDays($expiryDate->startOfDay()) : 0
            ]
        ], 200);
    }
}
