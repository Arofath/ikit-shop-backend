<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class HomeService
{
    public function getHomepageData()
    {
        return Cache::remember('home_page_data', 3600, function () {

            $recommended = Product::with(['thumbnail', 'brand'])
                ->where('is_active', true)
                ->where('is_recommended', true)
                ->orderByRaw('sort_order = 0, sort_order ASC') // 🌟 ១. ឱ្យវាគោរពលេខរៀង Admin
                ->latest()
                ->take(10)
                ->get();

            $newArrivals = Product::with(['thumbnail', 'brand'])
                ->where('is_active', true)
                ->latest('created_at')
                ->take(10)
                ->get();

            $topBrands = Brand::where('is_active', true)
                ->where('is_top', true)
                ->orderByRaw('sort_order = 0, sort_order ASC') // 🌟 ២. ឱ្យវាគោរពលេខរៀង Admin
                ->latest()
                ->take(6)
                ->get();

            return [
                'recommended'  => $recommended,
                'new_arrivals' => $newArrivals,
                'top_brands'    => $topBrands,
            ];
        });
    }
}
