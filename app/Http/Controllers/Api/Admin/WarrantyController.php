<?php

namespace App\Http\Controllers;

use App\Models\Warranty;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Warranty::latest()->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'duration_months' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'data' => Warranty::create($data)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Warranty $warranty)
    {
        return response()->json([
            'success' => true,
            'data' => $warranty
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Warranty $warranty)
    {
        $data = $request->validate([
            'duration_months' => 'sometimes|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $warranty->update($data);

        return response()->json([
            'message' => 'Warranty updated successfully',
            'data' => $warranty
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warranty $warranty)
    {
        $warranty->delete();

        return response()->json(['message' => 'Warranty deleted']);
    }
}
