<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSerial;
use App\Http\Resources\ProductSerialResource; // សន្មតថាអ្នកមាន Resource នេះ
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        // Filter តាមស្ថានភាព (AVAILABLE, SOLD, DEFECTIVE, RETURNED)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter តាមផលិតផល
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // 🌟 ដូរពី limit មក per_page ដើម្បីរក្សាស្តង់ដាររួម
        $serials = $query->latest()->paginate($request->get('per_page', 20));

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
        $serial = ProductSerial::with(['product.warranty', 'stockMovement.supplier', 'soldMovement'])
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

    /**
     * 🌟 មុខងារថ្មី៖ អនុញ្ញាតឱ្យ Admin ដូរស្ថានភាព Serial ណាមួយដោយដៃ (Manual Update)
     * ឧទាហរណ៍៖ ទំនិញខូចក្នុងស្តុក (DEFECTIVE) ឬ ភ្ញៀវយកមកដូរ (RETURNED)
     */
    public function updateStatus(Request $request, string $id)
    {
        $serial = ProductSerial::findOrFail($id);

        $request->validate([
            'status' => ['required', Rule::in(['AVAILABLE', 'SOLD', 'DEFECTIVE', 'RETURNED'])],
            'note'   => 'nullable|string|max:500' // អាចថែម note ប្រាប់ហេតុផល (បើចង់)
        ]);

        // ការពារមិនឱ្យដូរពី SOLD ទៅ AVAILABLE វិញផ្តេសផ្តាស (ត្រូវធ្វើតាមរយៈការដកស្តុកចេញ/ចូល)
        if ($serial->status === 'SOLD' && $request->status === 'AVAILABLE') {
            return $this->sendError('Action Denied.', ['Cannot manually change a SOLD item to AVAILABLE. Please process a return instead.'], 403);
        }

        $serial->update(['status' => $request->status]);

        return $this->sendResponse(
            new ProductSerialResource($serial),
            "Serial status updated to {$request->status} successfully."
        );
    }
}
