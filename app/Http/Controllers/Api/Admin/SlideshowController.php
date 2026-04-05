<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slideshow;
use App\Http\Resources\SlideshowResource; // សន្មតថាអ្នកមាន Resource នេះ
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CloudinaryStorageService; // 🌟 ហៅប្រើ Cloudinary Service

class SlideshowController extends Controller
{
    // ១. បង្ហាញបញ្ជី Slideshow ទាំងអស់សម្រាប់ Admin
    public function index()
    {
        $slides = Slideshow::with('series')->orderBy('position')->get();
        return $this->sendResponse(SlideshowResource::collection($slides), 'Slideshows retrieved.');
    }

    // ២. បង្កើត Slideshow ថ្មី
    public function store(Request $request, CloudinaryStorageService $storage)
    {
        $request->validate([
            'image'             => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'product_series_id' => 'nullable|exists:product_series,id',
            'position'          => 'nullable|integer|min:0',
            'is_active'         => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $storage) {

            // 🌟 ជួសជុល Bug: បើគ្មាន Slide ទេ max() នឹងស្មើ -1 រួចបូក 1 = 0
            $maxPosition = Slideshow::max('position') ?? -1;
            $requestedPosition = $request->filled('position') ? (int) $request->position : ($maxPosition + 1);

            // Shifting Logic
            Slideshow::where('position', '>=', $requestedPosition)->increment('position');

            // 🌟 Upload ទៅ Cloudinary ជាមួយ Transformation សម្រាប់ Banner
            $path = $storage->uploadImage(
                file: $request->file('image'),
                folder: 'slideshows',
                transformations: ['width' => 1920, 'height' => 800, 'crop' => 'fill', 'quality' => 'auto:best']
            );

            $slide = Slideshow::create([
                'image_path'        => $path,
                'product_series_id' => $request->product_series_id,
                'position'          => $requestedPosition,
                'is_active'         => $request->has('is_active') ? $request->boolean('is_active') : true,
            ]);

            return $this->sendResponse(new SlideshowResource($slide), 'Slideshow created and positions shifted.', 201);
        });
    }

    // ៣. កែសម្រួល Slideshow
    public function update(Request $request, string $id, CloudinaryStorageService $storage)
    {
        $slide = Slideshow::findOrFail($id);

        $request->validate([
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'product_series_id' => 'nullable|exists:product_series,id',
            'position'          => 'nullable|integer|min:0',
            'is_active'         => 'boolean', // ដូរពី nullable មក boolean
        ]);

        return DB::transaction(function () use ($request, $slide, $storage) {

            // 🌟 Logic កែប្រែរូបភាព (ប្រើប្រាស់ oldImageUrl ដើម្បីឱ្យ Service លុបដោយស្វ័យប្រវត្តិ)
            if ($request->hasFile('image')) {
                $slide->image_path = $storage->uploadImage(
                    file: $request->file('image'),
                    folder: 'slideshows',
                    oldImageUrl: $slide->image_path,
                    transformations: ['width' => 1920, 'height' => 800, 'crop' => 'fill', 'quality' => 'auto:best']
                );
            }

            // Logic រៀបលំដាប់លេខរៀង (Shifting Position)
            if ($request->filled('position') && (int) $request->position !== $slide->position) {
                $oldPos = $slide->position;
                $newPos = (int) $request->position;

                if ($newPos > $oldPos) {
                    Slideshow::whereBetween('position', [$oldPos + 1, $newPos])->decrement('position');
                } else {
                    Slideshow::whereBetween('position', [$newPos, $oldPos - 1])->increment('position');
                }
                $slide->position = $newPos;
            }

            // កែប្រែព័ត៌មានផ្សេងៗ
            if ($request->has('product_series_id')) {
                $slide->product_series_id = $request->product_series_id;
            }

            if ($request->has('is_active')) {
                // 🌟 ប្រើប្រាស់ boolean() របស់ Laravel
                $slide->is_active = $request->boolean('is_active');
            }

            $slide->save();

            return $this->sendResponse(new SlideshowResource($slide), 'Slideshow updated successfully.');
        });
    }

    // ៤. លុប Slideshow
    public function destroy(string $id, CloudinaryStorageService $storage)
    {
        $slide = Slideshow::findOrFail($id);
        $currentPosition = $slide->position;

        return DB::transaction(function () use ($slide, $storage, $currentPosition) {

            // 🌟 លុបរូបភាពពី Cloudinary
            if (!empty($slide->image_path)) {
                $storage->deleteImage($slide->image_path, 'slideshows');
            }

            $slide->delete();

            // ទាញលេខរៀងដែលនៅពីក្រោយ ឱ្យថយមកក្រោយ ១ លេខវិញ
            Slideshow::where('position', '>', $currentPosition)->decrement('position');

            return $this->sendResponse([], 'Slideshow deleted and positions reordered.');
        });
    }

    // ៥. បិទ/បើក ស្ថានភាព
    public function toggleStatus(string $id)
    {
        $slide = Slideshow::findOrFail($id);

        $slide->update([
            'is_active' => !$slide->is_active
        ]);

        return $this->sendResponse(
            new SlideshowResource($slide),
            'Slideshow status updated to ' . ($slide->is_active ? 'Active' : 'Inactive')
        );
    }

    // ៦. តម្រៀបលេខរៀងឡើងវិញដោយអូសទម្លាក់
    public function reorder(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:slideshows,id' // ត្រូវប្រាកដថាគ្រប់ ID សុទ្ធតែមាន
        ]);

        return DB::transaction(function () use ($request) {
            foreach ($request->ids as $index => $id) {
                Slideshow::where('id', $id)->update(['position' => $index]);
            }

            return $this->sendResponse([], 'Slideshow order updated successfully.');
        });
    }
}
