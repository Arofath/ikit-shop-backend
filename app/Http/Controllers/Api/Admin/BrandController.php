<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    // ១. បង្ហាញបញ្ជី Brand (ជាមួយ Pagination)
    public function index(Request $request)
    {
        $brands = Brand::latest()
            ->when($request->search, function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%");
            })
            ->paginate($request->get('per_page', 10));

        return $this->sendResponse(
            BrandResource::collection($brands)->response()->getData(true),
            'Brands retrieved successfully.'
        );
    }

    public function store(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'logo' => 'nullable|image|max:2048',
        ]);

        return DB::transaction(function () use ($request, $storage) {
            $logoUrl = null;
            if ($request->hasFile('logo')) {
                $logoUrl = $storage->uploadImage(
                    file: $request->file('logo'),
                    bucket: config('services.supabase.bucket_brand'), // ប្រើ config ជំនួស env
                    prefix: 'brands'
                );
            }

            $brand = Brand::create([
                'name'      => $request->name,
                'slug'      => Str::slug($request->name),
                'logo'      => $logoUrl,
                'is_active' => true,
            ]);

            return $this->sendResponse(new BrandResource($brand), 'Brand created successfully.', 201);
        });
    }

    public function show(string $id)
    {
        $brand = Brand::findOrFail($id);
        return $this->sendResponse(new BrandResource($brand), 'Brand detail retrieved.');
    }

    // ៤. កែសម្រួល Brand
    public function update(Request $request, string $id, SupabaseStorageService $storage)
    {
        $brand = Brand::findOrFail($id);

        $request->validate([
            'name'      => 'sometimes|required|string|max:255|unique:brands,name,' . $id,
            'logo'      => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $storage, $brand) {
            if ($request->hasFile('logo')) {
                $brand->logo = $storage->uploadImage(
                    file: $request->file('logo'),
                    bucket: config('services.supabase.bucket_brand'),
                    oldImageUrl: $brand->logo,
                    prefix: 'brands'
                );
            }

            $brand->update([
                'name'      => $request->name ?? $brand->name,
                'slug'      => $request->name ? Str::slug($request->name) : $brand->slug,
                'is_active' => $request->get('is_active', $brand->is_active),
                'logo'      => $brand->logo
            ]);

            return $this->sendResponse(new BrandResource($brand), 'Brand updated successfully.');
        });
    }

    // ៥. លុប Brand
    public function destroy(string $id, SupabaseStorageService $storage)
    {
        $brand = Brand::findOrFail($id);

        // Security Check: ការពារការលុបម៉ាកដែលមានផលិតផលជាប់ជាមួយ
        if ($brand->products()->exists()) {
            return $this->sendError('Cannot delete brand with associated products.', [], 400);
        }

        return DB::transaction(function () use ($brand, $storage) {
            if ($brand->logo) {
                $storage->deleteImage($brand->logo, config('services.supabase.bucket_brand'));
            }

            $brand->delete();
            return $this->sendResponse([], 'Brand deleted successfully.');
        });
    }
}
