<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Http\Resources\ProductImageResource;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductImageController extends Controller
{
    // ១. ទាញយករូបភាពទាំងអស់របស់ Product មួយ
    public function index(Product $product)
    {
        $images = $product->images()->orderBy('is_thumbnail', 'desc')->orderBy('sort_order', 'asc')->get();
        return $this->sendResponse(ProductImageResource::collection($images), 'Product images retrieved.');
    }

    // ២. Upload រូបភាពច្រើនសន្លឹក
    public function store(Request $request, Product $product, SupabaseStorageService $storage)
    {
        $request->validate([
            'images'   => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request, $product, $storage) {
            $uploadedImages = [];

            // ឆែកមើលថា តើ Product នេះមាន Thumbnail នៅឡើយ?
            $hasThumbnail = $product->images()->where('is_thumbnail', true)->exists();

            foreach ($request->file('images') as $index => $file) {
                $path = $storage->uploadImage(
                    file: $file,
                    bucket: config('services.supabase.bucket_product'),
                    prefix: "products/{$product->id}"
                );

                $image = $product->images()->create([
                    'image_path'   => $path,
                    'is_thumbnail' => (!$hasThumbnail && $index === 0), // បើគ្មាន Thumbnail ទេ រូបទី១ នឹងក្លាយជា Thumbnail
                    'sort_order'   => $index,
                ]);

                $uploadedImages[] = $image;
                if (!$hasThumbnail && $index === 0) $hasThumbnail = true;
            }

            return $this->sendResponse(
                ProductImageResource::collection($uploadedImages),
                'Images uploaded successfully.',
                201
            );
        });
    }

    // ៣. កំណត់រូបភាពណាមួយឱ្យទៅជា Thumbnail
    public function setThumbnail(string $id)
    {
        $image = ProductImage::findOrFail($id);

        return DB::transaction(function () use ($image) {
            // ដក Thumbnail ចាស់ចេញពីផលិតផលនេះទាំងអស់
            ProductImage::where('product_id', $image->product_id)
                ->update(['is_thumbnail' => false]);

            // កំណត់រូបភាពនេះជា Thumbnail ថ្មី
            $image->update(['is_thumbnail' => true]);

            return $this->sendResponse(new ProductImageResource($image), 'Thumbnail updated successfully.');
        });
    }

    // ៤. លុបរូបភាពសន្លឹកណាមួយ
    public function destroy(string $id, SupabaseStorageService $storage)
    {
        $image = ProductImage::findOrFail($id);

        return DB::transaction(function () use ($image, $storage) {
            // លុប File ក្នុង Supabase
            $storage->deleteImage($image->image_path, config('services.supabase.bucket_product'));

            // បើលុបចំរូបដែលជា Thumbnail យើងត្រូវរក្សាការការពារ (Optional: អ្នកអាចដាស់តឿន Admin)
            $wasThumbnail = $image->is_thumbnail;

            $image->delete();

            // បើលុបចំ Thumbnail ព្យាយាមកំណត់រូបដែលនៅសល់មួយទៀតជា Thumbnail
            if ($wasThumbnail) {
                $nextImage = ProductImage::where('product_id', $image->product_id)->first();
                if ($nextImage) {
                    $nextImage->update(['is_thumbnail' => true]);
                }
            }

            return $this->sendResponse([], 'Image deleted successfully.');
        });
    }
}
