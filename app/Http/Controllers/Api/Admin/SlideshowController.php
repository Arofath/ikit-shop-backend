<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slideshow;
use App\Http\Resources\SlideshowResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CloudinaryStorageService;

class SlideshowController extends Controller
{
    // ១. បង្ហាញបញ្ជី Slideshow ទាំងអស់សម្រាប់ Admin
    public function index()
    {
        // 🌟 ដក with('series') ចេញព្រោះយើងលែងប្រើវាហើយ
        $slides = Slideshow::orderBy('position')->get();
        return $this->sendResponse(SlideshowResource::collection($slides), 'Slideshows retrieved.');
    }

    // ២. បង្កើត Slideshow ថ្មី
    public function store(Request $request, CloudinaryStorageService $storage)
    {
        $request->validate([
            'image'     => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'link_url'  => 'nullable|string|max:1000', // 🌟 ដូរមក link_url
            'position'  => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $storage) {

            $maxPosition = Slideshow::max('position') ?? -1;
            $requestedPosition = $request->filled('position') ? (int) $request->position : ($maxPosition + 1);

            // Shifting Logic
            Slideshow::where('position', '>=', $requestedPosition)->increment('position');

            // Upload ទៅ Cloudinary
            $path = $storage->uploadImage(
                file: $request->file('image'),
                folder: 'slideshows',
                transformations: ['width' => 900, 'height' => 450, 'crop' => 'fill', 'quality' => 'auto:best']
            );

            $slide = Slideshow::create([
                'image_path' => $path,
                'link_url'   => $request->link_url, // 🌟 Save link_url
                'position'   => $requestedPosition,
                'is_active'  => $request->has('is_active') ? $request->boolean('is_active') : true,
            ]);

            return $this->sendResponse(new SlideshowResource($slide), 'Slideshow created and positions shifted.', 201);
        });
    }

    // ៣. កែសម្រួល Slideshow
    public function update(Request $request, string $id, CloudinaryStorageService $storage)
    {
        $slide = Slideshow::findOrFail($id);

        $request->validate([
            'image'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'link_url'  => 'nullable|string|max:1000', // 🌟 ដូរមក link_url
            'position'  => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $slide, $storage) {

            // Logic កែប្រែរូបភាព
            if ($request->hasFile('image')) {
                $slide->image_path = $storage->uploadImage(
                    file: $request->file('image'),
                    folder: 'slideshows',
                    oldImageUrl: $slide->image_path,
                    transformations: ['width' => 900, 'height' => 450, 'crop' => 'fill', 'quality' => 'auto:best']
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

            // 🌟 កែប្រែព័ត៌មាន Link URL
            // ប្រើ array_key_exists ដើម្បីអនុញ្ញាតឱ្យគេផ្ញើ link_url: null ដើម្បីលុប Link ចាស់ចោលបាន
            if (array_key_exists('link_url', $request->all())) {
                $slide->link_url = $request->link_url;
            }

            if ($request->has('is_active')) {
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

            if (!empty($slide->image_path)) {
                $storage->deleteImage($slide->image_path, 'slideshows');
            }

            $slide->delete();

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
            'ids.*' => 'exists:slideshows,id'
        ]);

        return DB::transaction(function () use ($request) {
            foreach ($request->ids as $index => $id) {
                Slideshow::where('id', $id)->update(['position' => $index]);
            }

            return $this->sendResponse([], 'Slideshow order updated successfully.');
        });
    }
}
