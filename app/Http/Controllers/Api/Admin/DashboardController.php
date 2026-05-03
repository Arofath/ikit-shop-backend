<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductStockMovement;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // ==========================================
        // ១. KPIs (Summary Cards) - E-commerce Focus
        // ==========================================
        $summary = [
            'total_revenue'   => 24500.50, // Mock: ចំណូលសរុបខែនេះ
            'total_orders'    => 1254,     // Mock: ចំនួន Order ខែនេះ
            'active_customers' => 890,      // Mock: អតិថិជនសកម្ម
            'pending_orders'  => 45,       // Mock: Order មិនទាន់ដោះស្រាយ

            // ទុកទិន្នន័យ Product ខ្លះក្រែងលោត្រូវការ
            'total_products'  => Product::count(),
        ];

        // ==========================================
        // ២. Chart Data (Revenue & Orders 6 ខែចុងក្រោយ)
        // ==========================================
        $chartData = [
            'labels'  => ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
            'revenue' => [18500, 21000, 19000, 24800, 22000, 24500.50],
            'orders'  => [850, 1050, 920, 1150, 1020, 1254]
        ];

        // ==========================================
        // ៣. Sales Activities (Recent Orders & Top Selling)
        // ==========================================
        $salesActivities = [
            // Mock: តារាង Order ថ្មីៗ
            'recent_orders' => [
                ['id' => '#ORD-001', 'customer' => 'Sok Dara', 'total' => 1250.00, 'status' => 'PENDING', 'date' => '2 mins ago'],
                ['id' => '#ORD-002', 'customer' => 'Chan Minea', 'total' => 85.50, 'status' => 'PAID', 'date' => '1 hour ago'],
                ['id' => '#ORD-003', 'customer' => 'John Doe', 'total' => 3400.00, 'status' => 'SHIPPED', 'date' => '3 hours ago'],
                ['id' => '#ORD-004', 'customer' => 'Meas Sreypich', 'total' => 450.00, 'status' => 'COMPLETED', 'date' => '5 hours ago'],
            ],

            // យក Product ពិតប្រាកដមកលាយជាមួយចំនួនលក់ក្លែងក្លាយ
            'top_selling_products' => Product::select('id', 'name', 'sku', 'price')
                ->with('thumbnail')
                ->latest()
                ->take(4) // យកតែ ៤ មុខ
                ->get()
                ->map(function ($product) {
                    $product->sold_qty = rand(50, 200); // Mock: ចំនួនលក់
                    return $product;
                })->sortByDesc('sold_qty')->values()
        ];

        // ==========================================
        // ៤. Secondary Info (Stock Alerts & Recent Customers)
        // ==========================================
        $stockSubquery = ProductStockMovement::selectRaw("COALESCE(SUM(CASE WHEN type IN ('IN', 'ADJUST') THEN quantity WHEN type = 'OUT' THEN -quantity ELSE 0 END), 0)")
            ->whereColumn('product_stock_movements.product_id', 'products.id');

        $productsWithStock = Product::select('id', 'name', 'sku')
            ->selectSub($stockSubquery, 'current_stock')
            ->having('current_stock', '<=', 5)
            ->with('thumbnail')
            ->get();

        $alerts = [
            // យកតែ ៤ មុខដែលជិតអស់ ឬអស់ស្តុក ដើម្បីកុំឱ្យចង្អៀត UI
            'low_stock' => $productsWithStock->where('current_stock', '>', 0)->take(4)->values(),
            'out_of_stock' => $productsWithStock->where('current_stock', '<=', 0)->take(4)->values(),
        ];

        $recentCustomers = [
            ['name' => 'Alice Smith', 'email' => 'alice@example.com', 'joined' => 'Today'],
            ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'joined' => 'Yesterday'],
            ['name' => 'Virak Roth', 'email' => 'virak.roth@example.com', 'joined' => '2 days ago'],
        ];

        // ==========================================
        // ៥. ផ្គុំទិន្នន័យបញ្ជូនទៅ Frontend
        // ==========================================
        return response()->json([
            'success' => true,
            'message' => 'E-commerce Dashboard data retrieved successfully.',
            'data' => [
                'summary'          => $summary,
                'chart_data'       => $chartData,
                'sales_activities' => $salesActivities,
                'alerts'           => $alerts,
                'recent_customers' => $recentCustomers
            ]
        ]);
    }
}
