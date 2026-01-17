<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['category', 'brand', 'thumbnail'])
            ->when(
                $request->search,
                fn($q) =>
                $q->where('name', 'ilike', "%{$request->search}%") // search by name
            )
            ->when(
                $request->category_id,
                fn($q) =>
                $q->where('category_id', $request->category_id) // filter by category
            )
            ->when(
                $request->brand_id,
                fn($q) =>
                $q->where('brand_id', $request->brand_id)
            )
            ->when(
                $request->is_active !== null,
                fn($q) =>
                $q->where('is_active', $request->is_active)
            )
            ->latest()
            ->paginate(10);

            return response()->json([
                'message' => 'Products fetched successfully',
                'success' => true,
                'data' => $products,
            ]);
    }

    /**
     * Show product by slug
     */
    public function showBySlug(string $slug)
    {
        $product = Product::where('slug', $slug)
            ->with([
                'category',
                'brand',
                'images',
                'specs'
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    // Admin
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'price' => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'product_series_id' => 'nullable|exists:product_series,id',
        ]);

        // Generate slug
        $slug = Str::slug($request->name);
        if (Product::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        $sku = $request->sku ?? 'SKU-' . strtoupper(Str::random(8));

        $product = Product::create([
            'name' => $request->name,
            'slug' => $slug,
            'sku' => $sku,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'price' => $request->price,
            'discount_percent' => $request->discount_percent,
            'description' => $request->description,
            'product_series_id' => $request->product_series_id ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $product
        ],201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|required|unique:products,sku,' . $id,
            'category_id' => 'sometimes|required|exists:categories,id',
            'brand_id' => 'sometimes|required|exists:brands,id',
            'price' => 'sometimes|required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'warranty_id' => 'nullable|exists:warranties,id',
        ]);

        if ($request->name && $request->name !== $product->name) {
            $product->slug = Str::slug($request->name) . '-' . Str::random(4);
        }

        $product->update($request->only([
            'name',
            'sku',
            'category_id',
            'brand_id',
            'price',
            'discount_percent',
            'description',
            'is_active',
            'warranty_id',
        ]));

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product->load('warranty'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}
