<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class HomeService
{
    public function getHomepageData()
    {
        return Cache::remember('home_page_data', 3600, function () {

            // ១. ទាញយក Recommended Products
            $recommended = Product::with(['thumbnail', 'brand'])
                ->where('is_active', true)
                ->where('is_recommended', true)
                ->orderByRaw('sort_order = 0, sort_order ASC')
                ->latest()
                ->take(10)
                ->get();

            // ២. ទាញយក New Arrivals
            $newArrivals = Product::with(['thumbnail', 'brand'])
                ->where('is_active', true)
                ->latest('created_at')
                ->take(10)
                ->get();

            // ៣. ទាញយក Top Brands
            $topBrands = Brand::where('is_active', true)
                ->where('is_top', true)
                ->orderByRaw('sort_order = 0, sort_order ASC')
                ->latest()
                ->take(6)
                ->get();

            // ៤. ទាញយក Popular Categories ជាមួយ Sub-Categories
            $popularCategories = Category::whereNull('parent_id') // យកតែ Category មេ
                ->where('is_active', true)
                ->where('is_popular', true)
                ->orderByRaw('sort_order = 0, sort_order ASC')
                ->latest()
                ->with(['subCategories' => function ($query) {
                    // ទាញយកកូនដែល Active និងតម្រៀបតាមលេខរៀង
                    $query->where('is_active', true)
                        ->orderByRaw('sort_order = 0, sort_order ASC')
                        ->latest();
                }])
                ->take(8) // យក Category មេតែ ៨ មុខ
                ->get()
                ->map(function ($category) {
                    // ប្រើប្រាស់វិធីសាស្ត្រនេះដើម្បីកំណត់ឱ្យយក Sub-categories តែ ៣ ក្នុងមួយមេ
                    $category->setRelation('parent', $category->subCategories->take(3));
                    return $category;
                });

            // បោះទិន្នន័យទាំងអស់ទៅកាន់ Frontend
            return [
                'recommended'        => $recommended,
                'new_arrivals'       => $newArrivals,
                'top_brands'         => $topBrands,
                'popular_categories' => $popularCategories,
            ];
        });
    }
}
