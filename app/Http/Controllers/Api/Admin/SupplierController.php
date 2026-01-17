<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Supplier::query();

        // 🔍 Search by name / phone / email
        if ($request->search) {
            $query->where('name', 'ilike', '%' . $request->search . '%')
                ->orWhere('phone', 'ilike', '%' . $request->search . '%')
                ->orWhere('email', 'ilike', '%' . $request->search . '%');
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|unique:suppliers,phone',
            'email'   => 'nullable|email|unique:suppliers,email',
            'address' => 'nullable|string',
            'status'  => 'boolean',
        ]);

        $supplier = Supplier::create($data);

        return response()->json([
            'success' => true,
            'data' => $supplier
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        return response()->json([
            'success' => true,
            'data' => $supplier
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name'    => 'sometimes|required|string|max:255',
            'phone'   => 'sometimes|required|string|unique:suppliers,phone,' . $supplier->id,
            'email'   => 'nullable|email|unique:suppliers,email,' . $supplier->id,
            'address' => 'nullable|string',
            'status'  => 'boolean',
        ]);

        $supplier->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Supplier updated successfully',
            'data' => $supplier
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully'
        ]);
    }
}
