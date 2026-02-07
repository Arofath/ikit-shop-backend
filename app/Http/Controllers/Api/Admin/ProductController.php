<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\SupabaseStorageService;

class ProductController extends Controller
{
    // ១. ទាញបញ្ជីផលិតផល (Admin & Public)
    public function index(Request $request)
    {
        $products = Product::query()
            ->with(['category', 'brand', 'thumbnail'])
            // ១. Search តាមឈ្មោះ ឬ SKU
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('sku', 'like', "%{$request->search}%");
            })
            // ២. Filter តាម Series Slug (សម្រាប់ឱ្យ Slide ដើរ)
            ->when($request->series, function ($q) use ($request) {
                $q->whereHas('productSeries', function ($query) use ($request) {
                    $query->where('slug', $request->series);
                });
            })
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->brand_id, fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->is_active))
            ->latest()
            ->paginate($request->per_page ?? 10);

        return $this->sendResponse(
            ProductResource::collection($products)->response()->getData(true),
            'Products fetched successfully'
        );
    }


    // ២. បង្ហាញតាមរយៈ Slug (សម្រាប់ Frontend)
    public function showBySlug(string $slug)
    {
        $product = Product::where('slug', $slug)
            ->with(['category', 'brand', 'images', 'specs', 'warranty', 'productSeries'])
            ->firstOrFail();

        return $this->sendResponse(new ProductResource($product), 'Product detail fetched');
    }

    // ៣. បង្កើតផលិតផលថ្មី
    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'sku'              => 'nullable|string|unique:products,sku',
            'category_id'      => 'required|exists:categories,id',
            'brand_id'         => 'required|exists:brands,id',
            'price'            => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'product_series_id' => 'nullable|exists:product_series,id',
            'warranty_id'      => 'nullable|exists:warranties,id',
        ]);

        return DB::transaction(function () use ($request) {
            // Logic បង្កើត Slug
            $slug = Str::slug($request->name);
            if (Product::where('slug', $slug)->exists()) {
                $slug .= '-' . Str::random(5);
            }

            // Logic បង្កើត SKU បើ Admin មិនបានដាក់
            $sku = $request->sku ?? strtoupper(substr(Str::slug($request->name), 0, 3)) . '-' . rand(1000, 9999);

            $product = Product::create([
                'name'              => $request->name,
                'slug'              => $slug,
                'sku'               => $sku,
                'category_id'       => $request->category_id,
                'brand_id'          => $request->brand_id,
                'price'             => $request->price,
                'discount_percent'  => $request->discount_percent ?? 0,
                'description'       => $request->description,
                'product_series_id' => $request->product_series_id,
                'warranty_id'       => $request->warranty_id,
                'is_active'         => true,
            ]);

            return $this->sendResponse(new ProductResource($product), 'Product created successfully', 201);
        });
    }

    // ៤. ទាញយក Detail តាម ID (សម្រាប់ Admin Edit)
    public function show(string $id)
    {
        $product = Product::with(['images', 'specs'])->findOrFail($id);
        return $this->sendResponse(new ProductResource($product), 'Product fetched');
    }

    // ៥. កែសម្រួលផលិតផល
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name'             => 'sometimes|required|string|max:255',
            'sku'              => 'sometimes|required|unique:products,sku,' . $id,
            'category_id'      => 'sometimes|required|exists:categories,id',
            'brand_id'         => 'sometimes|required|exists:brands,id',
            'price'            => 'sometimes|required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'is_active'        => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $product) {
            if ($request->has('name') && $request->name !== $product->name) {
                $product->slug = Str::slug($request->name) . '-' . Str::random(5);
            }

            $product->update($request->all());

            return $this->sendResponse(new ProductResource($product->load(['category', 'brand'])), 'Product updated successfully');
        });
    }

    // លុបបណ្ដោះអាសន្ន (Soft Delete)
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete(); // វានឹងដាក់ថ្ងៃខែក្នុង deleted_at តែរូបភាពមិនទាន់លុបទេ
        return $this->sendResponse([], 'Product moved to trash');
    }

    // លុបដាច់ពី System (Permanent Delete)
    public function forceDelete(string $id, SupabaseStorageService $storage)
    {
        // ត្រូវប្រើ withTrashed() ដើម្បីទាញយកទិន្នន័យដែលបាន Soft Delete ហើយ
        $product = Product::withTrashed()->with('images')->findOrFail($id);

        return DB::transaction(function () use ($product, $storage) {
            // ១. លុបរូបភាពទាំងអស់ពី Supabase
            foreach ($product->images as $image) {
                $storage->deleteImage($image->image_url, config('services.supabase.bucket_product'));
            }

            // ២. លុបទិន្នន័យចេញពី Database ជាស្ថាពរ
            $product->forceDelete();

            return $this->sendResponse([], 'Product and images permanently deleted');
        });
    }

    public function trash()
    {
        $products = Product::onlyTrashed()->with(['category', 'brand'])->get();
        return $this->sendResponse(ProductResource::collection($products), 'Trash list retrieved');
    }

    // យកផលិតផលមកវិញ
    public function restore(string $id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();
        return $this->sendResponse(new ProductResource($product), 'Product restored successfully');
    }
}
