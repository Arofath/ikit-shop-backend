<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Http\Resources\SupplierResource;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
        }

        $suppliers = $query->latest()->paginate($request->limit ?? 10);

        // ប្រើ getData(true) ដើម្បីបង្ហាញព័ត៌មាន Pagination ក្នុង Response
        return $this->sendResponse(
            SupplierResource::collection($suppliers)->response()->getData(true),
            'Suppliers retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:20|unique:suppliers,phone',
            'email'   => 'nullable|email|max:255|unique:suppliers,email',
            'address' => 'nullable|string|max:500',
            'status'  => 'boolean',
        ]);

        $supplier = Supplier::create($data);

        return $this->sendResponse(
            new SupplierResource($supplier),
            'Supplier created successfully.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        return $this->sendResponse(
            new SupplierResource($supplier),
            'Supplier details retrieved.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
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

        return $this->sendResponse(
            new SupplierResource($supplier),
            'Supplier updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        if ($supplier->stockMovements()->exists()) {
            return $this->sendError(
                'Validation Error.',
                ['Cannot delete this supplier because they have a history of stock transactions.'],
                400
            );
        }

        $supplier->delete();

        return $this->sendResponse([], 'Supplier deleted successfully.');
    }
}
