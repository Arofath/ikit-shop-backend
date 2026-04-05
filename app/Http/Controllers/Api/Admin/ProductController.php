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
            ->with(['category', 'brand', 'thumbnail']) // ល្អណាស់ ដែលហៅ thumbnail នៅទីនេះ!
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
            ->when($request->filled('category_id'), fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('brand_id'), fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active'))) // 🌟 ប្រើ boolean() ដើម្បីសុវត្ថិភាព
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
            ->with(['category', 'brand', 'images', 'specs', 'warranty', 'productSeries'])
            ->firstOrFail();

        return $this->sendResponse(new ProductResource($product), 'Product detail fetched.');
    }

    // ៣. បង្កើតផលិតផលថ្មី
    public function store(Request $request)
    {
        // រក្សាទុកទិន្នន័យដែលបាន Validate ចូលទៅក្នុង Variable មួយ
        $validatedData = $request->validate([
            'name'              => 'required|string|max:255',
            'sku'               => 'nullable|string|unique:products,sku',
            'category_id'       => 'required|exists:categories,id',
            'brand_id'          => 'required|exists:brands,id',
            'price'             => 'required|numeric|min:0',
            'discount_percent'  => 'nullable|numeric|min:0|max:100',
            'product_series_id' => 'nullable|exists:product_series,id',
            'warranty_id'       => 'nullable|exists:warranties,id',
        ]);

        return DB::transaction(function () use ($validatedData) {

            // 🌟 ប្រើប្រាស់ Helper ដើម្បីឱ្យប្រាកដថា Slug មិនជាន់គ្នា
            $validatedData['slug'] = $this->generateUniqueSlug($validatedData['name']);

            // Logic បង្កើត SKU បើ Admin មិនបានដាក់
            if (empty($validatedData['sku'])) {
                $validatedData['sku'] = strtoupper(substr(Str::slug($validatedData['name']), 0, 3)) . '-' . rand(10000, 99999);
            }

            // កំណត់ Default Value បើអត់មានបញ្ជូនមក
            $validatedData['discount_percent'] = $validatedData['discount_percent'] ?? 0;
            $validatedData['is_active'] = true;

            // 🌟 បង្កើត Product ដោយប្រើយ៉ាងសុវត្ថិភាពនូវទិន្នន័យដែលបាន Validate ហើយ
            $product = Product::create($validatedData);

            return $this->sendResponse(new ProductResource($product), 'Product created successfully.', 201);
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
            'category_id'       => 'sometimes|required|exists:categories,id',
            'brand_id'          => 'sometimes|required|exists:brands,id',
            'price'             => 'sometimes|required|numeric|min:0',
            'discount_percent'  => 'nullable|numeric|min:0|max:100',
            'product_series_id' => 'nullable|exists:product_series,id',
            'warranty_id'       => 'nullable|exists:warranties,id',
            'is_active'         => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $product, $validatedData) {

            // 🌟 បើកែឈ្មោះ ត្រូវកែ Slug ដែរ តែប្រើ Helper ដើម្បីការពារជាន់គ្នា
            if ($request->has('name') && $request->name !== $product->name) {
                $validatedData['slug'] = $this->generateUniqueSlug($request->name, $product->id);
            }

            // 🌟 Update ដោយប្រើប្រាស់ទិន្នន័យដែល Validate រួច មិនមែន $request->all() ទេ
            $product->update($validatedData);

            return $this->sendResponse(new ProductResource($product->load(['category', 'brand'])), 'Product updated successfully.');
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
            ->with(['category', 'brand'])
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
