<?php

namespace App\Services;

use App\Models\Product;

class ProductService
{
    /**
     * សម្រាប់ Storefront (Frontend) - អតិថិជនទូទៅ
     */
    public function getProductDetailBySlug(string $slug)
    {
        return Product::where('slug', $slug)
            ->where('is_active', true) // 🌟 ការពារមិនឱ្យអតិថិជនមើលទំនិញដែលបិទ (Disabled)
            ->with([
                'categories',
                'brand',
                'images',
                'specs',
                'warranty'
            ])
            ->firstOrFail();
    }

    public function getRelatedProductsBySlug(string $slug, int $limit = 5)
    {
        // ១. រកទំនិញដែលកំពុងមើលសិន ដើម្បីយក ID និង Categories របស់វា
        $currentProduct = Product::where('slug', $slug)->firstOrFail();

        // ២. ទាញយកតែលេខ ID របស់ Categories ទាំងអស់ដែលទំនិញនេះមាន
        $categoryIds = $currentProduct->categories->pluck('id');

        // ៣. ស្វែងរកទំនិញផ្សេងទៀត
        return Product::where('is_active', true)
            ->where('id', '!=', $currentProduct->id) // កុំឱ្យចេញទំនិញខ្លួនឯងជាន់គ្នា
            ->whereHas('categories', function ($query) use ($categoryIds) {
                // ត្រូវមាន Category ណាមួយដូចគ្នា
                $query->whereIn('categories.id', $categoryIds);
            })
            // 🌟 Eager Load តែអ្វីដែលចាំបាច់សម្រាប់បង្ហាញលើកាត (Product Card) 
            // មិនបាច់ with('specs') នាំតែធ្ងន់ទេ ព្រោះកាតអត់បានបង្ហាញ Spec ឡើយ
            ->with(['brand', 'images', 'categories'])
            ->inRandomOrder() // ច្របល់វា (Random) ដើម្បីឱ្យចេញប្លែកៗគ្នារាល់ពេល Refresh
            ->limit($limit)
            ->get();
    }

    public function getAllProducts($request)
    {
        $query = Product::where('is_active', true)
            ->with(['brand', 'categories', 'images']); // Join យកទិន្នន័យដែលចាំបាច់

        // ==========================================
        // 🌟 ១. មុខងារត្រងទិន្នន័យ (Filters)
        // ==========================================

        // ត្រងតាម Category Slug
        if ($request->has('category') && !empty($request->category)) {
            $categories = explode(',', $request->category); // បំបែកអក្សរជា Array
            $query->whereHas('categories', function ($q) use ($categories) {
                $q->whereIn('categories.slug', $categories); // ប្រើ whereIn ជំនួស where
            });
        }

        // ត្រងតាម Brand Slug
        if ($request->has('brand') && !empty($request->brand)) {
            $brands = explode(',', $request->brand);
            $query->whereHas('brand', function ($q) use ($brands) {
                $q->whereIn('brands.slug', $brands); // ប្រើ whereIn
            });
        }

        // ត្រងតាម Recommended (ពេលចុចពី Home Page)
        if ($request->has('filter') && $request->filter === 'recommended') {
            $query->where('is_recommended', true);
        }

        // ត្រងតាមចន្លោះតម្លៃ (Price Range)
        if ($request->has('min_price') && is_numeric($request->min_price)) {
            $query->whereRaw('(price - (price * (IFNULL(discount_percent, 0) / 100))) >= ?', [$request->min_price]);
        }

        if ($request->has('max_price') && is_numeric($request->max_price)) {
            $query->whereRaw('(price - (price * (IFNULL(discount_percent, 0) / 100))) <= ?', [$request->max_price]);
        }

        // ==========================================
        // 🌟 ២. មុខងារតម្រៀប (Sorting Logic)
        // ==========================================
        $sort = $request->input('sort', 'default'); // បើគ្មានគេបញ្ជាទេ ប្រើ default

        switch ($sort) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'price_asc':
                $query->orderByRaw('(price - (price * (IFNULL(discount_percent, 0) / 100))) ASC');
                break;
            case 'price_desc':
                $query->orderByRaw('(price - (price * (IFNULL(discount_percent, 0) / 100))) DESC');
                break;
            case 'popular':
                // បើមាន column view_count អាចប្រើវាបាន
                $query->orderBy('created_at', 'desc');
                break;
            case 'default':
            default:
                // 🌟 Industry Standard Hybrid Sort: រុញ Recommended ឡើងលើគេ បន្ទាប់មកទើបយក Newest
                $query->orderBy('is_recommended', 'desc')
                    ->orderBy('created_at', 'desc');
                break;
        }

        // ==========================================
        // 🌟 ៣. បែងចែកទំព័រ (Pagination)
        // ==========================================
        // យក ១២ ទំនិញក្នុងមួយទំព័រ
        return $query->paginate(12);
    }
}
