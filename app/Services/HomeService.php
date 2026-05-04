<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class HomeService
{
    public function getHomepageData()
    {
        // ដាក់ Cache ទុករយៈពេល ១ ម៉ោង (3600 វិនាទី) 
        // ឈ្មោះ Cache key: 'home_page_data'
        return Cache::remember('home_page_data', 3600, function () {

            $recommended = Product::with(['thumbnail', 'brand'])
                ->where('is_active', true)
                ->where('is_recommended', true)
                ->latest()
                ->take(8)
                ->get();

            $newArrivals = Product::with(['thumbnail', 'brand'])
                ->where('is_active', true)
                ->latest('created_at')
                ->take(8)
                ->get();

            return [
                'recommended'  => $recommended,
                'new_arrivals' => $newArrivals,
            ];
        });
    }
}
