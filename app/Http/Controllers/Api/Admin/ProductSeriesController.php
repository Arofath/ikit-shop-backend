<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSeries;
use Illuminate\Http\Request;

class ProductSeriesController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => ProductSeries::where('is_active', true)->get()
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:product_series,slug',
            'description' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'data' => ProductSeries::create($data)
        ], 201);
    }

    public function showBySlug(string $slug)
    {
        $series = ProductSeries::where('slug', $slug)
            ->with(['products' => function ($q) {
                $q->where('is_active', true);
            }])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $series
        ]);
    }

    public function update(Request $request, ProductSeries $productSeries)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'slug' => 'sometimes|unique:product_series,slug,' . $productSeries->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $productSeries->update($data);

        return response()->json([
            'message' => 'Series updated',
            'data' => $productSeries->fresh()
        ]);
    }

    public function destroy(ProductSeries $productSeries)
    {
        $productSeries->delete();

        return response()->json(['message' => 'Series deleted']);
    }
}
