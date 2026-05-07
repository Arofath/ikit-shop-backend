<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CloudinaryStorageService; // 🌟 ប្តូរមកប្រើ Cloudinary
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    // ១. ទាញបញ្ជីផលិតផល (Admin & Public)
    public function index(Request $request)
    {
        $products = Product::query()
            ->select('products.*')
            ->selectSub(function ($query) {
                $query->selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0)")
                    ->from('product_stock_movements')
                    ->whereColumn('product_stock_movements.product_id', 'products.id');
            }, 'current_stock')
            ->with(['categories', 'brand', 'thumbnail', 'serials'])
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
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->whereHas('categories', function ($query) use ($request) {
                    $query->where('categories.id', $request->category_id);
                });
            })
            ->when($request->filled('brand_id'), fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))

            // 🌟 ១. បន្ថែម Filter សម្រាប់ Storefront Layout
            ->when($request->has('is_recommended'), fn($q) => $q->where('is_recommended', $request->boolean('is_recommended')))

            // 🌟 ២. រៀបចំលំដាប់ (Sorting) ជំនួសឱ្យការប្រើត្រឹម ->latest()
            ->when($request->get('sort_by') === 'sort_order', function ($q) {
                // អ្នកអត់លេខ (0) ទៅក្រោមគេ ហើយរៀបលេខពីតូចទៅធំ
                $q->orderByRaw('sort_order = 0, sort_order ASC')->latest();
            }, function ($q) {
                // បើអត់បញ្ជូន sort_by មកទេ (ទំព័រ Product ធម្មតា) គឺប្រើចុងក្រោយគេ
                $q->latest();
            })
            ->paginate($request->get('per_page', 10));

        return $this->sendResponse(
            ProductResource::collection($products)->response()->getData(true),
            'Products fetched successfully.'
        );
    }

    // API សម្រាប់ Storefront (បង្ហាញលើ Website)
    public function showBySlug(string $slug)
    {
        $product = $this->productService->getProductDetailBySlug($slug);

        return $this->sendResponse(new ProductResource($product), 'Product detail fetched.');
    }

    public function getRelatedProducts(string $slug)
    {
        $relatedProducts = $this->productService->getRelatedProductsBySlug($slug);

        // យើងប្រើ ProductResource::collection ព្រោះវាជា Array នៃទំនិញច្រើន
        return $this->sendResponse(
            ProductResource::collection($relatedProducts),
            'Related products fetched successfully.'
        );
    }

    // ៣. បង្កើតផលិតផលថ្មី
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name'              => 'required|string|max:255',
            'sku'               => 'nullable|string|unique:products,sku',
            'description'       => 'nullable|string',
            'category_ids'      => 'required|array|min:1',
            'category_ids.*'    => 'exists:categories,id',
            'brand_id'          => 'required|exists:brands,id',
            'price'             => 'required|numeric|min:0',
            'discount_percent'  => 'nullable|numeric|min:0|max:100',
            'warranty_id'       => 'nullable|exists:warranties,id',
            'is_active'         => 'boolean',
            'is_serialized'     => 'boolean',
            'is_recommended'    => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $validatedData) {
            $validatedData['slug'] = $this->generateUniqueSlug($validatedData['name']);

            // បង្កើត SKU ស្វ័យប្រវត្តិបើអត់មានបញ្ជូនមក (លុបកូដដែលជាន់គ្នាចោល)
            if (empty($validatedData['sku'])) {
                $validatedData['sku'] = strtoupper(substr(Str::slug($validatedData['name']), 0, 3)) . '-' . rand(10000, 99999);
            }

            $validatedData['discount_percent'] = $validatedData['discount_percent'] ?? 0;

            // 🌟 ដំណោះស្រាយ: ប្រើ boolean() របស់ Laravel ដើម្បីយកតម្លៃពិតពី Frontend (បើអត់មានបញ្ជូនមក យក True ជា Default)
            $validatedData['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

            // 🌟 ដំណោះស្រាយ: កំណត់ Serialized (បើអត់មានបញ្ជូនមក យក False ជា Default ព្រោះទំនិញភាគច្រើនអត់មាន Serial ទេ)
            $validatedData['is_serialized'] = $request->has('is_serialized') ? $request->boolean('is_serialized') : false;

            $validatedData['is_recommended'] = $request->has('is_recommended') ? $request->boolean('is_recommended') : false;

            // ដកយក category_ids ចេញពី Array សិន មុននឹង Save
            $categoryIds = $validatedData['category_ids'];
            unset($validatedData['category_ids']);

            // បង្កើត Product
            $product = Product::create($validatedData);

            // ភ្ជាប់ Categories
            $product->categories()->sync($categoryIds);

            Cache::forget('home_page_data');

            return $this->sendResponse(new ProductResource($product->load('categories')), 'Product created successfully.', 201);
        });
    }

    // ៤. ទាញយក Detail តាម ID (សម្រាប់ Admin Edit)
    public function show(string $id)
    {
        $product = Product::with(['categories', 'brand', 'images', 'specs'])->findOrFail($id);
        return $this->sendResponse(new ProductResource($product), 'Product fetched.');
    }

    // ៥. កែសម្រួលផលិតផល
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $validatedData = $request->validate([
            'name'              => 'sometimes|required|string|max:255',
            'sku'               => 'sometimes|required|string|unique:products,sku,' . $id,
            'description'       => 'nullable|string',
            'category_ids'      => 'sometimes|required|array|min:1',
            'category_ids.*'    => 'exists:categories,id',
            'brand_id'          => 'sometimes|required|exists:brands,id',
            'price'             => 'sometimes|required|numeric|min:0',
            'discount_percent'  => 'nullable|numeric|min:0|max:100',
            'warranty_id'       => 'nullable|exists:warranties,id',
            'is_active'         => 'boolean',
            'is_serialized'     => 'boolean',
            'is_recommended'    => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $product, $validatedData) {
            if ($request->has('name') && $request->name !== $product->name) {
                $validatedData['slug'] = $this->generateUniqueSlug($request->name, $product->id);
            }

            // 🌟 ធានាថា Boolean ត្រូវបាន Convert ត្រឹមត្រូវ (ការពារបញ្ហាបញ្ជូនតម្លៃមកជាអក្សរ "true" ឬ "false" ពី Axios)
            if ($request->has('is_active')) {
                $validatedData['is_active'] = $request->boolean('is_active');
            }
            if ($request->has('is_serialized')) {
                $validatedData['is_serialized'] = $request->boolean('is_serialized');
            }

            if ($request->has('is_recommended')) {
                $validatedData['is_recommended'] = $request->boolean('is_recommended');
            }

            // ឆែកមើលថាតើមានការបញ្ជូនកែប្រែ Categories ដែរឬទេ
            if (isset($validatedData['category_ids'])) {
                $categoryIds = $validatedData['category_ids'];
                unset($validatedData['category_ids']);
                $product->categories()->sync($categoryIds);
            }

            $product->update($validatedData);
            Cache::forget('home_page_data');
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

    public function getStats()
    {
        // ១. រាប់ចំនួនសរុប និង ចំនួនដែលកំពុង Active
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();

        // ២. បង្កើត Subquery សម្រាប់គណនាស្តុកបច្ចុប្បន្នរបស់ទំនិញនីមួយៗ
        $stockSubquery = Product::select('id')->selectSub(function ($query) {
            $query->selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0)")
                ->from('product_stock_movements')
                ->whereColumn('product_stock_movements.product_id', 'products.id');
        }, 'current_stock');

        // ៣. រាប់ចំនួនទំនិញដែល Low Stock (មានស្តុកចន្លោះពី ១ ដល់ ៥)
        $lowStock = DB::query()
            ->fromSub($stockSubquery, 'stock_data')
            ->where('current_stock', '>', 0)
            ->where('current_stock', '<=', 5)
            ->count();

        // ៤. រាប់ចំនួនទំនិញដែល Out of Stock (អស់ស្តុក ស្មើ ០ ឬ ក្រោម ០)
        $outOfStock = DB::query()
            ->fromSub($stockSubquery, 'stock_data')
            ->where('current_stock', '<=', 0)
            ->count();

        // បោះទិន្នន័យត្រឡប់ទៅឱ្យ Vue វិញ
        return $this->sendResponse([
            'total_products'  => $totalProducts,
            'active_products' => $activeProducts,
            'low_stock'       => $lowStock,
            'out_of_stock'    => $outOfStock,
        ], 'Product stats retrieved successfully.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:products,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        // ប្រើប្រាស់ Transaction ដើម្បីធានាថាវា Update ជោគជ័យទាំងអស់ ទើប Save
        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                // Update លេខរៀងម្តងមួយៗ (លឿនគ្រប់គ្រាន់សម្រាប់ទិន្នន័យ ១០ ទៅ ៥០ មុខ)
                Product::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        Cache::forget('home_page_data');

        return response()->json([
            'success' => true,
            'message' => 'Product order updated successfully.'
        ]);
    }
}
