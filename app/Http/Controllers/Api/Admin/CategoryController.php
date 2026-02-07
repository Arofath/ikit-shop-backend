<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\SupabaseStorageService;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    // List categories as tree
    public function index()
    {
        $categories = Category::whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->orderBy('name', 'asc');
            }])
            ->orderBy('name')
            ->get();

        return $this->sendResponse(
            CategoryResource::collection($categories),
            'Categories tree retrieved successfully.'
        );
    }

    // Show category detail
    public function show(string $id)
    {
        $category = Category::with('children')->findOrFail($id);

        return $this->sendResponse(
            new CategoryResource($category),
            'Category retrieved successfully.'
        );
    }

    // Store category
    public function store(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|max:2048',
        ]);


        return DB::transaction(function () use ($request, $storage) {
            $publicUrl = null;
            if ($request->hasFile('image')) {
                $publicUrl = $storage->uploadImage(
                    file: $request->file('image'),
                    bucket: config('services.supabase.bucket_category'),
                    prefix: 'category'
                );
            }

            $category = Category::create([
                'name'      => $request->name,
                'slug'      => $this->generateUniqueSlug($request->name),
                'image'     => $publicUrl,
                'parent_id' => $request->parent_id,
                'is_active' => true,
            ]);

            return $this->sendResponse(new CategoryResource($category), 'Category created successfully.', 201);
        });
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
        // ការពារមិនឱ្យយកខ្លួនឯងធ្វើជា Parent
        if ($request->parent_id === $id) {
            return $this->sendError('A category cannot be its own parent.', [], 400);
        }

        return DB::transaction(function () use ($request, $storage, $category) {
            if ($request->has('name')) {
                $category->name = $request->name;
                $category->slug = $this->generateUniqueSlug($request->name, $category->id);
            }

            if ($request->hasFile('image')) {
                $category->image = $storage->uploadImage(
                    file: $request->file('image'),
                    bucket: config('services.supabase.bucket_category'),
                    oldImageUrl: $category->image,
                    prefix: 'category'
                );
            }

            $category->is_active = $request->get('is_active', $category->is_active);
            $category->parent_id = $request->get('parent_id', $category->parent_id);
            $category->save();

            return $this->sendResponse(new CategoryResource($category), 'Category updated successfully.');
        });
    }

    // Delete category
    public function destroy(string $id, SupabaseStorageService $storage)
    {
        $category = Category::findOrFail($id);

        if ($category->children()->exists()) {
            return $this->sendError('Cannot delete category with subcategories.', [], 400);
        }

        // លុបរូបភាពពី Supabase
        if ($category->image) {
            $storage->deleteImage($category->image, config('services.supabase.bucket_category'));
        }

        $category->delete();
        return $this->sendResponse([], 'Category deleted successfully.');
    }

    // Generate unique slug
    // Helper: បង្កើត Slug ដែលមិនជាន់គ្នា
    private function generateUniqueSlug($name, $id = null)
    {
        $slug = Str::slug($name);
        $count = Category::where('slug', $slug)->when($id, fn($q) => $q->where('id', '!=', $id))->count();
        return $count > 0 ? "{$slug}-" . time() : $slug;
    }
}
