<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Http\Resources\ProductImageResource;
use App\Services\CloudinaryStorageService; // 🌟 ប្តូរមកប្រើ Cloudinary
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductImageController extends Controller
{
    // ១. ទាញយករូបភាពទាំងអស់របស់ Product មួយ
    public function index(Product $product)
    {
        $images = $product->images()
            ->orderBy('is_thumbnail', 'desc')
            ->orderBy('sort_order', 'asc')
            ->get();

        return $this->sendResponse(ProductImageResource::collection($images), 'Product images retrieved.');
    }

    // ២. Upload រូបភាពច្រើនសន្លឹក
    public function store(Request $request, Product $product, CloudinaryStorageService $storage)
    {
        // 🌟 ការពារ Timeout ពេល Upload រូបច្រើន (កំណត់ ៥ នាទី)
        set_time_limit(300);

        $request->validate([
            'images'   => 'required|array|min:1|max:10', // ដាក់កំណត់ត្រឹម ១០ សន្លឹកម្តងដើម្បីកុំឱ្យធ្ងន់ពេក
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        return DB::transaction(function () use ($request, $product, $storage) {
            $uploadedImages = [];

            // ឆែកមើលថា តើ Product នេះមាន Thumbnail នៅឡើយ?
            $hasThumbnail = $product->images()->where('is_thumbnail', true)->exists();

            // កំណត់ Sort Order បន្តពីរូបចុងក្រោយ (បើមាន)
            $lastSortOrder = $product->images()->max('sort_order') ?? -1;

            foreach ($request->file('images') as $index => $file) {
                // 🌟 ប្រើប្រាស់ Cloudinary និងកំណត់ Transformation ឱ្យចេញជារាងការ៉េស្អាត
                $path = $storage->uploadImage(
                    file: $file,
                    folder: 'products',
                    transformations: ['width' => 1000, 'height' => 1000, 'crop' => 'pad', 'background' => 'white', 'quality' => 'auto:best']
                );

                $lastSortOrder++;
                $isCurrentThumbnail = (!$hasThumbnail && $index === 0);

                $image = $product->images()->create([
                    'image_path'   => $path,
                    'is_thumbnail' => $isCurrentThumbnail, // បើគ្មាន Thumbnail ទេ រូបទី១ នឹងក្លាយជា Thumbnail
                    'sort_order'   => $lastSortOrder,
                ]);

                $uploadedImages[] = $image;

                if ($isCurrentThumbnail) {
                    $hasThumbnail = true;
                }
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
                ->where('is_thumbnail', true) // បន្ថែមលក្ខខណ្ឌនេះដើម្បី Update តែ Record ដែលជា true
                ->update(['is_thumbnail' => false]);

            // កំណត់រូបភាពនេះជា Thumbnail ថ្មី
            $image->update(['is_thumbnail' => true]);

            return $this->sendResponse(new ProductImageResource($image), 'Thumbnail updated successfully.');
        });
    }

    // ៤. លុបរូបភាពសន្លឹកណាមួយ
    public function destroy(string $id, CloudinaryStorageService $storage)
    {
        $image = ProductImage::findOrFail($id);

        return DB::transaction(function () use ($image, $storage) {

            // 🌟 លុប File ក្នុង Cloudinary
            if (!empty($image->image_path)) {
                $storage->deleteImage($image->image_path, 'products');
            }

            $wasThumbnail = $image->is_thumbnail;
            $productId = $image->product_id;

            $image->delete();

            // បើលុបចំ Thumbnail ព្យាយាមកំណត់រូបដែលនៅសល់មួយទៀតជា Thumbnail
            if ($wasThumbnail) {
                $nextImage = ProductImage::where('product_id', $productId)->first();
                if ($nextImage) {
                    $nextImage->update(['is_thumbnail' => true]);
                }
            }

            return $this->sendResponse([], 'Image deleted successfully.');
        });
    }
}
