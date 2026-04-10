<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\CloudinaryStorageService; // 🌟 ប្តូរមកប្រើ Cloudinary

class ProductController extends Controller
{
    // ១. ទាញបញ្ជីផលិតផល (Admin & Public)
    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['categories', 'brand', 'thumbnail'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where(function ($inner) use ($request) {
                    $inner->where('name', 'like', "%{$request->search}%")
                        ->orWhere('sku', 'like', "%{$request->search}%");
                });
            })
            ->when($request->filled('series'), function ($q) use ($request) {
                $q->whereHas('productSeries', function ($query) use ($request) {
                    $query->where('slug', $request->series);
                });
            })
            // 🌟 កែប្រែពី where('category_id') ទៅជា whereHas សម្រាប់ Many-to-Many
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->whereHas('categories', function ($query) use ($request) {
                    $query->where('categories.id', $request->category_id);
                });
            })
            ->when($request->filled('brand_id'), fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate($request->get('per_page', 10));

        return $this->sendResponse(
            ProductResource::collection($products)->response()->getData(true),
            'Products fetched successfully.'
        );
    }

    // ២. បង្ហាញតាមរយៈ Slug (សម្រាប់ Frontend)
    public function showBySlug(string $slug)
    {
        $product = Product::where('slug', $slug)
            ->with(['categories', 'brand', 'images', 'specs', 'warranty', 'productSeries'])
            ->firstOrFail();

        return $this->sendResponse(new ProductResource($product), 'Product detail fetched.');
    }

    // ៣. បង្កើតផលិតផលថ្មី
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'              => 'required|string|max:255',
            'sku'               => 'nullable|string|unique:products,sku',
            // 🌟 ដូរពី category_id ទៅជា category_ids (Array)
            'category_ids'      => 'required|array|min:1',
            'category_ids.*'    => 'exists:categories,id', // ឆែកមើលថាលេខ ID និមួយៗពិតជាមានក្នុង DB
            'brand_id'          => 'required|exists:brands,id',
            'price'             => 'required|numeric|min:0',
            'discount_percent'  => 'nullable|numeric|min:0|max:100',
            'product_series_id' => 'nullable|exists:product_series,id',
            'warranty_id'       => 'nullable|exists:warranties,id',
        ]);

        return DB::transaction(function () use ($validatedData) {
            $validatedData['slug'] = $this->generateUniqueSlug($validatedData['name']);

            if (empty($validatedData['sku'])) {
                $validatedData['sku'] = strtoupper(substr(Str::slug($validatedData['name']), 0, 3)) . '-' . rand(10000, 99999);
            }

            $validatedData['discount_percent'] = $validatedData['discount_percent'] ?? 0;
            $validatedData['is_active'] = true;

            // 🌟 ដកយក category_ids ចេញពី Array សិន មុននឹង Save ចូលតារាង products
            $categoryIds = $validatedData['category_ids'];
            unset($validatedData['category_ids']);

            // បង្កើត Product
            $product = Product::create($validatedData);

            // 🌟 ភ្ជាប់ Categories ច្រើនទៅកាន់ Product (Save ចូល Pivot Table)
            $product->categories()->sync($categoryIds);

            // Load យក Categories មកវិញដើម្បីបង្ហាញក្នុង Response
            return $this->sendResponse(new ProductResource($product->load('categories')), 'Product created successfully.', 201);
        });
    }

    // ៤. ទាញយក Detail តាម ID (សម្រាប់ Admin Edit)
    public function show(string $id)
    {
        $product = Product::with(['images', 'specs'])->findOrFail($id);
        return $this->sendResponse(new ProductResource($product), 'Product fetched.');
    }

    // ៥. កែសម្រួលផលិតផល
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $validatedData = $request->validate([
            'name'              => 'sometimes|required|string|max:255',
            'sku'               => 'sometimes|required|string|unique:products,sku,' . $id,
            // 🌟 កែប្រែ Validation សម្រាប់ Update ដែរ
            'category_ids'      => 'sometimes|required|array|min:1',
            'category_ids.*'    => 'exists:categories,id',
            'brand_id'          => 'sometimes|required|exists:brands,id',
            'price'             => 'sometimes|required|numeric|min:0',
            'discount_percent'  => 'nullable|numeric|min:0|max:100',
            'product_series_id' => 'nullable|exists:product_series,id',
            'warranty_id'       => 'nullable|exists:warranties,id',
            'is_active'         => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $product, $validatedData) {
            if ($request->has('name') && $request->name !== $product->name) {
                $validatedData['slug'] = $this->generateUniqueSlug($request->name, $product->id);
            }

            // 🌟 ឆែកមើលថាតើមានការបញ្ជូនកែប្រែ Categories ដែរឬទេ
            if (isset($validatedData['category_ids'])) {
                $categoryIds = $validatedData['category_ids'];
                unset($validatedData['category_ids']);

                // Update ទំនាក់ទំនងក្នុង Pivot Table
                $product->categories()->sync($categoryIds);
            }

            $product->update($validatedData);

            return $this->sendResponse(new ProductResource($product->load(['categories', 'brand'])), 'Product updated successfully.');
        });
    }

    // ៦. លុបបណ្ដោះអាសន្ន (Soft Delete)
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return $this->sendResponse([], 'Product moved to trash.');
    }

    // ៧. លុបដាច់ពី System (Permanent Delete)
    public function forceDelete(string $id, CloudinaryStorageService $storage)
    {
        $product = Product::withTrashed()->with('images')->findOrFail($id);

        return DB::transaction(function () use ($product, $storage) {

            // 🌟 លុបរូបភាពទាំងអស់ពី Cloudinary
            if ($product->images) {
                foreach ($product->images as $image) {
                    // សន្មតថា ProductImage model របស់អ្នកមាន column 'image_url'
                    if (!empty($image->image_url)) {
                        $storage->deleteImage($image->image_url, 'products');
                    }
                }
            }

            $product->forceDelete();

            return $this->sendResponse([], 'Product and all associated images permanently deleted.');
        });
    }

    // ៨. មើលបញ្ជីធុងសំរាម
    public function trash(Request $request)
    {
        // 🌟 ដូរពី get() មក paginate() ដើម្បី Performance
        $products = Product::onlyTrashed()
            ->with(['categories', 'brand'])
            ->latest('deleted_at')
            ->paginate($request->get('per_page', 10));

        return $this->sendResponse(
            ProductResource::collection($products)->response()->getData(true),
            'Trash list retrieved.'
        );
    }

    // ៩. យកផលិតផលមកវិញ
    public function restore(string $id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        return $this->sendResponse(new ProductResource($product), 'Product restored successfully.');
    }

    // --- Helper Method ---

    // បង្កើត Slug មិនឱ្យជាន់គ្នា (ដូចនៅក្នុង Category ដែរ)
    private function generateUniqueSlug($name, $id = null)
    {
        $slug = Str::slug($name);
        $count = Product::where('slug', $slug)->when($id, fn($q) => $q->where('id', '!=', $id))->count();
        return $count > 0 ? "{$slug}-" . time() : $slug;
    }
}
