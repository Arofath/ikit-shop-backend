<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\SupabaseStorageService;

class CategoryController extends Controller
{
    // List categories as tree
    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->with('children.children') // support deep nesting
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    // Show category detail
    public function show(string $id)
    {
        $category = Category::with('children')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    // Store category
    public function store(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);


        $publicUrl = $storage->uploadImage(
            file: $request->file('image'),
            bucket: env('SUPABASE_CATEGORY_BUCKET'),
            prefix: 'category'
        );
        
        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'image' => $publicUrl,
            'parent_id' => $request->parent_id,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    // Update category
    public function update(Request $request, string $id, SupabaseStorageService $storage)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if($request->parent_id === $id) {
            return response()->json([
                'message' => 'Cannot set parent category to itself'
            ], 400);
        }

        $publicUrl = $storage->uploadImage(
            file: $request->file('image'),
            bucket: env('SUPABASE_CATEGORY_BUCKET'),
            oldImageUrl: $category->image,
            prefix: 'category'
        );

        $category->update([
            'name' => $request->name ?? $category->name,
            'slug' => $request->name ? Str::slug($request->name) : $category->slug,
            'image' => $publicUrl ?? $category->image,
            'parent_id' => $request->parent_id,
            'is_active' => $request->is_active ?? $category->is_active,
        ]);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    // Delete category
    public function destroy(string $id, SupabaseStorageService $storage)
    {
        $category = Category::findOrFail($id);

        if ($category->children()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category with subcategories',
            ], 400);
        }

        $storage->deleteImage($category->image, env('SUPABASE_CATEGORY_BUCKET'));
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }

}
