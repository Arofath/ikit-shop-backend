<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductSerial;

class PublicWarrantyController extends Controller
{
    public function check(Request $request)
    {
        $request->validate([
            'serial_number' => 'required|string'
        ]);

        $serial = ProductSerial::with(['product.warranty', 'soldMovement'])
            ->where('serial_number', $request->serial_number)
            ->where('status', 'SOLD') // ឆែកបានតែអីវ៉ាន់ដែលលក់រួច
            ->first();

        if (!$serial) {
            return $this->sendError('Serial number not found or not yet sold.', [], 404);
        }

        return $this->sendResponse([
            'serial_number'   => $serial->serial_number,
            'product_name'    => $serial->product->name,
            'purchase_date'   => $serial->soldMovement->created_at->format('Y-m-d'),
            'expiry_date'     => $serial->warranty_expiry_date ? $serial->warranty_expiry_date->format('Y-m-d') : 'N/A',
            'warranty_status' => $serial->warranty_status, // Active or Expired
            'warranty_type'   => $serial->product->warranty->name ?? 'N/A',
        ], 'Warranty information retrieved.');
    }
}
