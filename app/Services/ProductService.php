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
}
