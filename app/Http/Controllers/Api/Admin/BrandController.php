<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\CloudinaryStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    // ១. បង្ហាញបញ្ជី Brand (រក្សាដដែល)
    public function index(Request $request)
    {
        $brands = Brand::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                return $query->where('name', 'like', "%{$request->search}%");
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                return $query->where('is_active', $request->boolean('is_active'));
            })

            // 🌟 ប្តូរពី has ទៅ filled ដូចគ្នា
            ->when($request->filled('is_top'), function ($query) use ($request) {
                return $query->where('is_top', $request->boolean('is_top'));
            })

            ->when($request->get('sort_by') === 'sort_order', function ($q) {
                $q->orderByRaw('sort_order = 0, sort_order ASC')->latest();
            }, function ($q) {
                $q->latest();
            })
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Brands retrieved successfully.',
            'data' => BrandResource::collection($brands)->response()->getData(true),
        ], 200);
    }

    // ២. បង្កើត Brand ថ្មី (គ្មានការ Upload រូបភាពទៀតទេ)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'is_top'    => 'boolean',
        ]);

        $brand = Brand::create([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name),
            'is_active' => true,
            'is_top'    => $request->has('is_top') ? $request->boolean('is_top') : false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Brand created successfully. You can now upload a logo.',
            'data' => new BrandResource($brand)
        ], 201);
    }

    // ៣. មើលព័ត៌មាន Brand (រក្សាដដែល)
    public function show(string $id)
    {
        $brand = Brand::findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Brand details retrieved.',
            'data' => new BrandResource($brand)
        ], 200);
    }

    // ៤. កែសម្រួល Brand (មានតែ Text ប៉ុណ្ណោះ)
    public function update(Request $request, string $id)
    {
        $brand = Brand::findOrFail($id);

        $request->validate([
            'name'      => 'sometimes|required|string|max:255|unique:brands,name,' . $id,
            'is_active' => 'boolean',
            'is_top'    => 'boolean',
        ]);

        $updateData = [];

        if ($request->filled('name') && $request->name !== $brand->name) {
            $updateData['name'] = $request->name;
            $updateData['slug'] = Str::slug($request->name);
        }

        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }

        if ($request->has('is_top')) {
            $updateData['is_top'] = $request->boolean('is_top');
        }

        if (!empty($updateData)) {
            $brand->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully.',
            'data' => new BrandResource($brand)
        ], 200);
    }

    // 🌟 ៥. មុខងារថ្មីដាច់ដោយឡែក៖ Upload Logo សម្រាប់ Brand ណាមួយ
    public function uploadLogo(Request $request, string $id, CloudinaryStorageService $storage)
    {
        set_time_limit(120);

        $brand = Brand::findOrFail($id);

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        try {
            // Upload រូបភាពថ្មី ហើយលុបរូបចាស់ចោល (បើមាន)
            $logoUrl = $storage->uploadImage(
                file: $request->file('logo'),
                folder: 'brands',
                oldImageUrl: $brand->logo,
                transformations: ['width' => 400, 'height' => 400, 'crop' => 'pad', 'background' => 'white', 'quality' => 'auto:best']
            );

            // Update តែ Field `logo` ប៉ុណ្ណោះ
            $brand->update(['logo' => $logoUrl]);

            return response()->json([
                'success' => true,
                'message' => 'Brand logo uploaded successfully.',
                'data' => new BrandResource($brand)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logo upload failed.',
                'errors'  => app()->environment('local') ? ['error' => $e->getMessage()] : []
            ], 500);
        }
    }

    // ៦. លុប Brand
    public function destroy(string $id, CloudinaryStorageService $storage)
    {
        $brand = Brand::findOrFail($id);

        if ($brand->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete brand with associated products.'
            ], 400);
        }

        return DB::transaction(function () use ($brand, $storage) {
            if ($brand->logo) {
                $storage->deleteImage($brand->logo, 'brands');
            }

            $brand->delete();

            return response()->json([
                'success' => true,
                'message' => 'Brand deleted successfully.'
            ], 200);
        });
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:brands,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->items as $item) {
                Brand::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });

        Cache::forget('home_page_data');

        return response()->json([
            'success' => true,
            'message' => 'Brand order updated successfully.'
        ]);
    }
}
