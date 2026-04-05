<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductSerial;
use App\Models\ProductStockMovement;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // ==========================================
        // ១. ទិន្នន័យសង្ខេប (Summary Cards / KPIs)
        // ==========================================
        $summary = [
            'total_products'   => Product::count(),
            'active_products'  => Product::where('is_active', true)->count(),
            'total_categories' => Category::count(),
            'total_brands'     => Brand::count(),

            // ទិន្នន័យស្តុក
            'available_serials' => ProductSerial::where('status', 'AVAILABLE')->count(),
            'defective_serials' => ProductSerial::where('status', 'DEFECTIVE')->count(),

            // 🌟 ទិន្នន័យបណ្ដោះអាសន្ន (Mock Data) សម្រាប់ Order
            'orders' => [
                'total_orders'    => 1254,
                'pending_orders'  => 45,
                'monthly_revenue' => 24500.50, // គិតជាដុល្លារ
                'total_customers' => 890,
            ]
        ];

        // ==========================================
        // ២. ការដាស់តឿនស្តុក (Inventory Alerts)
        // ==========================================
        // ប្រើប្រាស់ Subquery ដ៏មានប្រសិទ្ធភាពដើម្បីទាញយកស្តុកបច្ចុប្បន្ន
        $stockSubquery = ProductStockMovement::selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0)")
            ->whereColumn('product_stock_movements.product_id', 'products.id');

        $productsWithStock = Product::select('id', 'name', 'sku')
            ->selectSub($stockSubquery, 'current_stock')
            // ទាញយកតែអ្នកដែលស្តុកក្រោម ឬស្មើ ៥ (ជិតអស់ ឬអស់)
            ->having('current_stock', '<=', 5)
            ->with('thumbnail') // ភ្ជាប់រូបភាពមកជាមួយ
            ->get();

        // បំបែកជា ២ ក្រុម៖ ជិតអស់ (១ ទៅ ៥) និង អស់ស្តុក (<= ០)
        $lowStock = $productsWithStock->where('current_stock', '>', 0)->values();
        $outOfStock = $productsWithStock->where('current_stock', '<=', 0)->values();

        $alerts = [
            'low_stock'    => $lowStock,
            'out_of_stock' => $outOfStock,
        ];

        // ==========================================
        // ៣. សកម្មភាពថ្មីៗ (Recent Activities)
        // ==========================================
        $recentActivities = [
            // ស្តុកដែលទើបតែមានចលនាចេញចូល ៨ ចុងក្រោយ
            'recent_stock_movements' => ProductStockMovement::with(['product:id,name,sku', 'supplier:id,name'])
                ->latest()
                ->take(8)
                ->get(),

            // ផលិតផលដែលទើបតែបន្ថែមថ្មីៗ ៥ ចុងក្រោយ
            'recently_added_products' => Product::select('id', 'name', 'sku', 'price', 'created_at')
                ->with('thumbnail')
                ->latest()
                ->take(5)
                ->get(),
        ];

        // ==========================================
        // ៤. ផ្គុំទិន្នន័យបញ្ជូនទៅ Frontend
        // ==========================================
        return $this->sendResponse([
            'summary'    => $summary,
            'alerts'     => $alerts,
            'activities' => $recentActivities
        ], 'Dashboard data retrieved successfully.');
    }
}
