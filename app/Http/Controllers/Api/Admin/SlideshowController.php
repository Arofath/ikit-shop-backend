<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slideshow;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlideshowController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Slideshow::with('series:id,name,slug')
                ->orderBy('position')
                ->get()
        ]);
    }

    public function show(Slideshow $slideshow)
    {
        return response()->json([
            'success' => true,
            'data' => $slideshow->load('series:id,name,slug')
        ]);
    }

    public function active()
    {
        return response()->json([
            'success' => true,
            'data' => Slideshow::with('series:id,name,slug')
                ->where('is_active', true)
                ->orderBy('position')
                ->get()
        ]);
    }

    public function store(Request $request, SupabaseStorageService $storage)
    {
        $request->validate([
            'product_series_id' => 'nullable|exists:product_series,id',
            'image' => 'required|image|max:4096',
            'position' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $image = $storage->uploadImage(
            file: $request->file('image'),
            bucket: env('SUPABASE_SLIDESHOW_BUCKET'),
            prefix: 'slideshow'
        );

        $data = Slideshow::create([
            'product_series_id' => $request->product_series_id,
            'image' => $image,
            'position' => $request->position ?? Slideshow::max('position') + 1,
            'is_active' => $request->is_active ?? true
        ]);

        return response()->json([
            'success' => true,
            'data' => $data
        ], 201);
    }

    public function update(Request $request, Slideshow $slideshow, SupabaseStorageService $storage)
    {
        $request->validate([
            'product_series_id' => 'nullable|exists:product_series,id',
            'image' => 'sometimes|image|max:4096',
            'position' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $image = $slideshow->image;

        if ($request->hasFile('image')) {
            $image = $storage->uploadImage(
                file: $request->file('image'),
                bucket: env('SUPABASE_SLIDESHOW_BUCKET'),
                oldImageUrl: $slideshow->image,
                prefix: 'slideshow'
            );
        }

        $slideshow->update([
            'product_series_id' => $request->product_series_id ?? $slideshow->product_series_id,
            'image' => $image,
            'position' => $request->position ?? $slideshow->position,
            'is_active' => $request->is_active ?? $slideshow->is_active,
        ]);

        return response()->json([
            'message' => 'Slideshow updated',
            'data' => $slideshow
        ]);
    }

    public function destroy(Slideshow $slideshow, SupabaseStorageService $storage)
    {
        $storage->deleteImage($slideshow->image, env('SUPABASE_SLIDESHOW_BUCKET'));
        $slideshow->delete();
        return response()->json(['message' => 'Slideshow deleted']);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:slideshows,id',
            'orders.*.position' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->orders as $item) {
                Slideshow::where('id', $item['id'])->update(['position' => $item['position']]);
            }
        });

        return response()->json([
            'message' => 'Slideshow reordered successfully'
        ]);
    }

    public function toggle(Slideshow $slideshow)
    {
        $slideshow->update([
            'is_active' => !$slideshow->is_active
        ]);

        return response()->json([
            'message' => 'Status updated',
            'data' => $slideshow
        ]);
    }
}
