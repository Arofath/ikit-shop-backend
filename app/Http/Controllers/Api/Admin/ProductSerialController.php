<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductSerial;
use App\Http\Resources\ProductSerialResource;

class ProductSerialController extends Controller
{
    /**
     * បង្ហាញបញ្ជី Serial ទាំងអស់ (ជាមួយ Filter)
     */
    public function index(Request $request)
    {
        $query = ProductSerial::with(['product', 'stockMovement.supplier', 'soldMovement']);

        // ស្វែងរកតាម Serial Number (សម្រាប់ Scan)
        if ($request->filled('search')) {
            $query->where('serial_number', 'LIKE', "%{$request->search}%");
        }

        // Filter តាមស្ថានភាព (AVAILABLE, SOLD)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter តាមផលិតផល
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $serials = $query->latest()->paginate($request->limit ?? 20);

        return $this->sendResponse(
            ProductSerialResource::collection($serials)->response()->getData(true),
            'Product serials retrieved successfully.'
        );
    }

    /**
     * មុខងារពិសេស៖ ឆែកព័ត៌មាន Serial សម្រាប់ធ្វើ Warranty
     */
    public function checkWarranty($serialNumber)
    {
        $serial = ProductSerial::with(['product', 'stockMovement.supplier', 'soldMovement'])
            ->where('serial_number', $serialNumber)
            ->first();

        if (!$serial) {
            return $this->sendError('Serial number not found.', [], 404);
        }

        return $this->sendResponse(
            new ProductSerialResource($serial),
            'Serial information found.'
        );
    }
}
