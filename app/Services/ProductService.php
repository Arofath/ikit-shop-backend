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
}
