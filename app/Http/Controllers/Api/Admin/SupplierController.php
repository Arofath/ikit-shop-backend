<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Http\Resources\SupplierResource; // សន្មតថាអ្នកមាន Resource នេះ
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::query()
            ->withCount('stockMovements')
            ->when($request->filled('search'), function ($q) use ($request) {
                // រុំ function ទប់កុំឱ្យ orWhere ប៉ះពាល់លក្ខខណ្ឌផ្សេងទៀត
                $q->where(function ($inner) use ($request) {
                    $inner->where('name', 'LIKE', "%{$request->search}%")
                        ->orWhere('phone', 'LIKE', "%{$request->search}%")
                        ->orWhere('email', 'LIKE', "%{$request->search}%");
                });
            })
            // ប្រើប្រាស់ filled និង boolean() របស់ Laravel ផ្ទាល់
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->boolean('status'));
            })
            ->latest()
            ->paginate($request->get('per_page', 10)); // ប្តូរមកប្រើ per_page ជាស្តង់ដារ

        return $this->sendResponse(
            SupplierResource::collection($suppliers)->response()->getData(true),
            'Suppliers retrieved successfully.'
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:20|unique:suppliers,phone',
            'email'   => 'nullable|email|max:255|unique:suppliers,email',
            'address' => 'nullable|string|max:500',
            'status'  => 'boolean',
        ]);

        // កំណត់ Status ជា true ជានិច្ចបើអត់មានបញ្ជូនមក
        $data['status'] = $request->has('status') ? $request->boolean('status') : true;

        $supplier = Supplier::create($data);

        return $this->sendResponse(new SupplierResource($supplier), 'Supplier created successfully.', 201);
    }

    public function show(Supplier $supplier)
    {
        return $this->sendResponse(new SupplierResource($supplier), 'Supplier details retrieved.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name'    => 'sometimes|required|string|max:255',
            'phone'   => 'sometimes|required|string|max:20|unique:suppliers,phone,' . $supplier->id,
            'email'   => 'nullable|email|max:255|unique:suppliers,email,' . $supplier->id,
            'address' => 'nullable|string|max:500',
            'status'  => 'boolean',
        ]);

        $supplier->update($data);

        return $this->sendResponse(new SupplierResource($supplier), 'Supplier updated successfully.');
    }

    // លុបបណ្ដោះអាសន្ន (Soft Delete)
    public function destroy(Supplier $supplier)
    {
        if ($supplier->stockMovements()->exists()) {
            return $this->sendError(
                'Validation Error.',
                ['error' => 'Cannot delete this supplier because they have a history of stock transactions.'],
                400
            );
        }

        $supplier->delete();

        return $this->sendResponse([], 'Supplier moved to trash.');
    }

    // 🌟 បន្ថែម៖ មើលបញ្ជីធុងសំរាម
    public function trash(Request $request)
    {
        $suppliers = Supplier::onlyTrashed()
            ->latest('deleted_at')
            ->paginate($request->get('per_page', 10));

        return $this->sendResponse(
            SupplierResource::collection($suppliers)->response()->getData(true),
            'Trashed suppliers retrieved.'
        );
    }

    // 🌟 បន្ថែម៖ យកមកវិញពីធុងសំរាម
    public function restore(string $id)
    {
        $supplier = Supplier::withTrashed()->findOrFail($id);
        $supplier->restore();

        return $this->sendResponse(new SupplierResource($supplier), 'Supplier restored successfully.');
    }

    // 🌟 បន្ថែម៖ លុបដាច់ជាស្ថាពរ
    public function forceDelete(string $id)
    {
        $supplier = Supplier::withTrashed()->findOrFail($id);

        // ឆែកមើលម្តងទៀតមុនលុបដាច់
        if ($supplier->stockMovements()->exists()) {
            return $this->sendError('Cannot permanently delete this supplier due to existing stock transactions.', [], 400);
        }

        $supplier->forceDelete();

        return $this->sendResponse([], 'Supplier permanently deleted.');
    }
}
