<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $brands = Brand::latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $brands,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'logo' => 'nullable|image|max:2048',
        ]);

        $logoUrl = null;

        if ($request->hasFile('logo')) {
            $logoUrl = $storage->uploadImage(
                file: $request->file('logo'),
                bucket: env('SUPABASE_BRAND_BUCKET'),
                prefix: 'brands'
            );
        }

        $brand = Brand::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'logo' => $logoUrl,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Brand created successfully',
            'data' => $brand,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $brand = Brand::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $brand,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, SupabaseStorageService $storage)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,' . $id,
            'logo' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        $brand = Brand::findOrFail($id);
        $logoUrl = $brand->logo;
        // ✅ Only upload if logo exists
        if ($request->hasFile('logo')) {
            $logoUrl = $storage->uploadImage(
                file: $request->file('logo'),
                bucket: env('SUPABASE_BRAND_BUCKET'),
                oldImageUrl: $brand->logo,
                prefix: 'brands'
            );
        }

        $brand->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'logo' => $logoUrl,
            'is_active' =>  $request->is_active ?? $brand->is_active,
        ]);

        return response()->json([
            'message' => 'Brand updated successfully',
            'data' => $brand,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, SupabaseStorageService $storage)
    {
        $brand = Brand::findOrFail($id);

        if ($brand->products()->exists()) {
            return response()->json([
                'message' => 'Cannot delete brand with products',
            ], 400);
        }

        $storage->deleteImage($brand->logo, env('SUPABASE_BRAND_BUCKET'));
        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully',
        ]); 
    }
}
