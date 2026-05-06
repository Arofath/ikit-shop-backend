<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Slideshow;
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
                // 🌟 ១. ប្តូរពី subCategories ទៅជា children
                ->with(['children' => function ($query) {
                    // ទាញយកកូនដែល Active និងតម្រៀបតាមលេខរៀង
                    $query->where('is_active', true)
                        ->where('is_popular', true)
                        ->orderByRaw('sort_order = 0, sort_order ASC')
                        ->latest();
                }])
                ->take(8) // យក Category មេតែ ៨ មុខ
                ->get()
                ->map(function ($category) {
                    // 🌟 ២. ប្តូរទៅកំណត់ relation 'children' វិញ
                    $category->setRelation('children', $category->children->take(3));
                    return $category;
                });

            $sidebarCategories = Category::whereNull('parent_id') // យកតែមេ
                ->where('is_active', true)
                // 🚫 មិនបាច់ដាក់លក្ខខណ្ឌ is_popular ទេ ព្រោះចង់បង្ហាញទាំងអស់
                ->orderByRaw('sort_order = 0, sort_order ASC')
                ->latest()
                ->with(['children' => function ($query) {
                    // ទាញយកកូនទាំងអស់ដែល Active (មិនកំណត់ត្រឹម ៣ មុខទេ)
                    $query->where('is_active', true)
                        ->orderByRaw('sort_order = 0, sort_order ASC')
                        ->latest();
                }])
                ->get(); // ទាញយកទាំងអស់ (បើមានច្រើនពេក អាចដាក់ ->take(10) ទៅតាមតម្រូវការ)

            $slideshows = Slideshow::where('is_active', true)
                ->orderByRaw('sort_order = 0, sort_order ASC') // តម្រៀបតាមលេខរៀង Admin
                ->latest()
                ->get();

            // បោះទិន្នន័យទាំងអស់ទៅកាន់ Frontend
            return [
                'recommended'        => $recommended,
                'new_arrivals'       => $newArrivals,
                'top_brands'         => $topBrands,
                'popular_categories' => $popularCategories,
                'sidebar_categories' => $sidebarCategories,
                'slideshows'         => $slideshows,
            ];
        });
    }
}
