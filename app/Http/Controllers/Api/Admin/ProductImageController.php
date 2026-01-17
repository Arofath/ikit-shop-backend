<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductImage;
use App\Models\Product;
use App\Services\SupabaseStorageService;

class ProductImageController extends Controller
{
    public function index($productId)
    {
        $images = ProductImage::where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $images
        ]);
    }

    public function store(Request $request, Product $product, SupabaseStorageService $storage)
    {
        $request->validate([
            'image' => 'required',
            'image.*' => 'image|max:2048',
            'is_thumbnail' => 'nullable|boolean',
        ]);

        $files = $request->file('image');
        if (!is_array($files)) {
            $files = [$files];
        }

        $createdImages = [];
        $lastOrder = $product->images()->max('sort_order') ?? 0;

        foreach ($files as $index => $file) {

            $imagePath = $storage->uploadImage(
                file: $file,
                bucket: env('SUPABASE_PRODUCT_BUCKET'),
                prefix: 'products'
            );

            if ($request->boolean('is_thumbnail') && $index === 0) {
                $product->images()->update(['is_thumbnail' => false]);
                $isThumbnail = true;
            } else {
                $isThumbnail = false;
            }

            $createdImages[] = ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'is_thumbnail' => $isThumbnail,
                'sort_order' => $lastOrder + $index + 1,
            ]);
        }

        return response()->json([
            'message' => 'Images uploaded successfully',
            'data' => $createdImages,
        ], 201);
    }



    public function setThumbnail($id)
    {
        $image = ProductImage::findOrFail($id);

        ProductImage::where('product_id', $image->product_id)
            ->update(['is_thumbnail' => false]);

        $image->update(['is_thumbnail' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Thumbnail updated'
        ]);
    }

    public function destroy($id)
    {
        $image = ProductImage::findOrFail($id);

        // Delete file
        $storage = new SupabaseStorageService();
        $storage->deleteImage($image->image_path, env('SUPABASE_PRODUCT_BUCKET'));

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image deleted'
        ]);
    }
}
