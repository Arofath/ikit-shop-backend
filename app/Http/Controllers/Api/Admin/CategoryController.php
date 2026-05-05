<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\CloudinaryStorageService; // 🌟 ហៅប្រើ Cloudinary Service

class CategoryController extends Controller
{
    // List categories as tree
    public function index(Request $request)
    {
        // ១. ចាប់យកតម្លៃ status ពី URL (?status=active ឬ ?status=inactive)
        $status = $request->query('status');

        // ២. ចាប់ផ្តើមសរសេរ Query សម្រាប់ Category មេ
        $query = Category::whereNull('parent_id');

        // ៣. ដាក់លក្ខខណ្ឌ Filter សម្រាប់ Category មេ
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // ៤. ទាញយកកូនៗ (Children) ព្រមទាំងដាក់លក្ខខណ្ឌ Filter ទៅឲ្យកូនៗដូចគ្នា
        $query->with(['children' => function ($q) use ($status) {
            $q->orderBy('name', 'asc');

            // បើគេចង់បានតែ Active កូនៗក៏ត្រូវតែ Active ដែរ
            if ($status === 'active') {
                $q->where('is_active', true);
            } elseif ($status === 'inactive') {
                $q->where('is_active', false);
            }
        }]);

        // ៥. បញ្ចប់ Query ដោយតម្រៀបឈ្មោះ និងទាញយកទិន្នន័យ
        $categories = $query->orderBy('name')->get();

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

    // Store category (គ្មានការ Upload រូបភាពទៀតទេ)
    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'is_popular' => 'boolean',          
            'sort_order' => 'nullable|integer',
        ]);

        $category = Category::create([
            'name'      => $request->name,
            'slug'      => $this->generateUniqueSlug($request->name),
            'parent_id' => $request->parent_id,
            'is_active' => $request->boolean('is_active', true),
            'is_popular' => $request->boolean('is_popular', false),
            'sort_order' => $request->get('sort_order', 0),
        ]);

        return $this->sendResponse(new CategoryResource($category), 'Category created successfully.', 201);
    }

    // Update category (មានតែ Text ប៉ុណ្ណោះ)
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name'      => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        // ការពារមិនឱ្យយកខ្លួនឯងធ្វើជា Parent
        if ($request->has('parent_id') && $request->parent_id === $id) {
            return $this->sendError('A category cannot be its own parent.', [], 400);
        }

        $updateData = [];

        if ($request->filled('name') && $request->name !== $category->name) {
            $updateData['name'] = $request->name;
            $updateData['slug'] = $this->generateUniqueSlug($request->name, $category->id);
        }

        if ($request->has('parent_id')) {
            $updateData['parent_id'] = $request->parent_id;
        }

        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }

        if ($request->has('is_popular')) {
            $updateData['is_popular'] = $request->boolean('is_popular');
        }

        if ($request->has('sort_order')) {
            $updateData['sort_order'] = $request->get('sort_order');
        }

        if (!empty($updateData)) {
            $category->update($updateData);
        }

        return $this->sendResponse(new CategoryResource($category), 'Category updated successfully.');
    }

    // 🌟 មុខងារថ្មី៖ Upload Image សម្រាប់ Category
    public function uploadImage(Request $request, string $id, CloudinaryStorageService $storage)
    {
        set_time_limit(120);

        $category = Category::findOrFail($id);

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        try {
            $imageUrl = $storage->uploadImage(
                file: $request->file('image'),
                folder: 'categories',
                oldImageUrl: $category->image,
                // កំណត់ទំហំសម្រាប់ Category Image
                transformations: ['width' => 800, 'height' => 800, 'crop' => 'limit', 'quality' => 'auto']
            );

            $category->update(['image' => $imageUrl]);

            return $this->sendResponse(new CategoryResource($category), 'Category image uploaded successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Image upload failed.', ['error' => app()->environment('local') ? $e->getMessage() : ''], 500);
        }
    }

    // Delete category
    public function destroy(string $id, CloudinaryStorageService $storage)
    {
        $category = Category::findOrFail($id);

        if ($category->children()->exists()) {
            return $this->sendError('Cannot delete category with subcategories.', [], 400);
        }

        // ការពារការលុប Category ដែលមាន Product ជាប់
        if ($category->products()->exists()) {
            return $this->sendError('Cannot delete category with associated products.', [], 400);
        }

        return DB::transaction(function () use ($category, $storage) {
            // លុបរូបភាពពី Cloudinary
            if ($category->image) {
                $storage->deleteImage($category->image, 'categories');
            }

            $category->delete();
            return $this->sendResponse([], 'Category deleted successfully.');
        });
    }

    // Helper: បង្កើត Slug ដែលមិនជាន់គ្នា
    private function generateUniqueSlug($name, $id = null)
    {
        $slug = Str::slug($name);
        $count = Category::where('slug', $slug)->when($id, fn($q) => $q->where('id', '!=', $id))->count();
        return $count > 0 ? "{$slug}-" . time() : $slug;
    }
}
