<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slideshow;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\SlideshowResource;

class SlideshowController extends Controller
{
    // ១. បង្ហាញបញ្ជី Slideshow ទាំងអស់សម្រាប់ Admin
    public function index()
    {
        $slides = Slideshow::with('series')->orderBy('position')->get();
        return $this->sendResponse(SlideshowResource::collection($slides), 'Slideshows retrieved.');
    }

    // ២. បង្កើត Slideshow ថ្មី
    public function store(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'image'             => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'product_series_id' => 'nullable|exists:product_series,id',
            'position'          => 'nullable|integer|min:0',
            'is_active'         => 'boolean',
        ]);

        return DB::transaction(function () use ($request, $storage) {
            $requestedPosition = $request->position ?? (Slideshow::max('position') + 1);

            // --- បច្ចេកទេសកុំឱ្យជាន់គ្នា (Shifting Logic) ---
            // រាល់ Slide ណាដែលមានលេខរៀងធំជាង ឬស្មើ លេខដែលទើបនឹងបញ្ចូល 
            // វានឹងត្រូវបូកថែម ១ (increment) ដើម្បីទុកកន្លែងឱ្យ Slide ថ្មីនេះ
            Slideshow::where('position', '>=', $requestedPosition)->increment('position');

            // Upload រូបភាពទៅ Supabase
            $path = $storage->uploadImage(
                file: $request->file('image'),
                bucket: config('services.supabase.bucket_slideshow'),
                prefix: 'banners'
            );

            $slide = Slideshow::create([
                'image_path'        => $path,
                'product_series_id' => $request->product_series_id,
                'position'          => $requestedPosition,
                'is_active'         => $request->is_active ?? true,
            ]);

            return $this->sendResponse(new SlideshowResource($slide), 'Slideshow created and positions shifted.', 201);
        });
    }

    public function update(Request $request, string $id, SupabaseStorageService $storage)
    {
        $slide = Slideshow::findOrFail($id);

        // ១. ការកំណត់លក្ខខណ្ឌ Validation
        $request->validate([
            'image'             => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'product_series_id' => 'nullable|exists:product_series,id',
            'position'          => 'nullable|integer|min:0',
            'is_active'         => 'nullable',
        ]);

        return DB::transaction(function () use ($request, $slide, $storage) {
            // ២. Logic កែប្រែរូបភាព (ដូររូបចាស់ចេញ បើមានរូបថ្មី)
            if ($request->hasFile('image')) {
                // លុបរូបចាស់ពី Supabase
                $storage->deleteImage($slide->image_path, config('services.supabase.bucket_slideshow'));

                // Upload រូបថ្មី
                $slide->image_path = $storage->uploadImage(
                    file: $request->file('image'),
                    bucket: config('services.supabase.bucket_slideshow'),
                    prefix: 'banners'
                );
            }

            // ៣. Logic រៀបលំដាប់លេខរៀង (Shifting Position)
            if ($request->has('position') && $request->position != $slide->position) {
                $oldPos = $slide->position;
                $newPos = (int) $request->position;

                if ($newPos > $oldPos) {
                    // បើប្តូរពីលេខតូចទៅធំ (ឧ៖ ១ -> ៣) ត្រូវទាញលេខកណ្តាលថយក្រោយ
                    Slideshow::whereBetween('position', [$oldPos + 1, $newPos])->decrement('position');
                } else {
                    // បើប្តូរពីលេខធំមកតូច (ឧ៖ ៣ -> ១) ត្រូវរុញលេខកណ្តាលទៅមុខ
                    Slideshow::whereBetween('position', [$newPos, $oldPos - 1])->increment('position');
                }
                $slide->position = $newPos;
            }

            // ៤. កែប្រែព័ត៌មានផ្សេងៗ
            if ($request->has('product_series_id')) {
                $slide->product_series_id = $request->product_series_id;
            }

            if ($request->has('is_active')) {
                // បំប្លែង "true"/"1" ឱ្យទៅជា Boolean ពិតប្រាកដ
                $slide->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            }

            $slide->save();

            return $this->sendResponse(new SlideshowResource($slide), 'Slideshow updated successfully.');
        });
    }

    // ៤. លុប Slideshow
    public function destroy(string $id, SupabaseStorageService $storage)
    {
        $slide = Slideshow::findOrFail($id);
        $currentPosition = $slide->position;

        return DB::transaction(function () use ($slide, $storage, $currentPosition) {
            // កែសម្រួល៖ ប្រើ config ឱ្យដូច store/update ដើម្បីកុំឱ្យមានបញ្ហា permission ឬបាត់ file
            $storage->deleteImage($slide->image_path, config('services.supabase.bucket_slideshow'));

            $slide->delete();

            // ទាញលេខរៀងដែលនៅពីក្រោយ ឱ្យថយមកក្រោយ ១ លេខវិញ
            Slideshow::where('position', '>', $currentPosition)->decrement('position');

            return $this->sendResponse([], 'Slideshow deleted and positions reordered.');
        });
    }

    public function toggleStatus(string $id)
    {
        $slide = Slideshow::findOrFail($id);

        // ប្តូរពី true ទៅ false ឬពី false ទៅ true
        $slide->update([
            'is_active' => !$slide->is_active
        ]);

        return $this->sendResponse(
            new SlideshowResource($slide),
            'Slideshow status updated to ' . ($slide->is_active ? 'Active' : 'Inactive')
        );
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
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
