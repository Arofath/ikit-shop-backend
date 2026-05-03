<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        // ១. ទាញយកទំនិញណែនាំ (Recommended Products)
        $recommendedProducts = Product::with('thumbnail', 'brand') // ភ្ជាប់រូបភាព និងម៉ាក
            ->where('is_active', true)
            ->where('is_recommended', true)
            ->latest() // យកអ្នកដែល Admin ទើបតែ Tick ថ្មីៗមុនគេ
            ->take(8)  // យកតែ ៨ មុខ ដើម្បីបង្ហាញលើផ្ទាំងកណ្តាល
            ->get();

        // ២. ទាញយកទំនិញថ្មីៗ (New Arrivals)
        $newArrivals = Product::with('thumbnail', 'brand')
            ->where('is_active', true)
            ->latest('created_at') // តម្រៀបតាមថ្ងៃខែបញ្ចួលថ្មីបំផុត
            ->take(8) // យកត្រឹម ៨ មុខ
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'recommended' => $recommendedProducts,
                'new_arrivals' => $newArrivals,
            ]
        ]);
    }
}
