<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // ==========================================
        // ១. KPIs (Summary Cards) - Real Data
        // ==========================================
        $summary = [
            // 🌟 កែមកប្រើ grand_total និងឆែកមើល payment_status = PAID សម្រាប់ចំណូល
            'total_revenue'   => Order::where('payment_status', 'PAID')->sum('grand_total'),

            'total_orders'    => Order::count(),

            'active_customers' => User::where('role', 'customer')->count(),

            'pending_orders'  => Order::where('status', 'PENDING')->count(),

            'total_products'  => Product::count(),
        ];

        // ==========================================
        // ២. Chart Data (Revenue & Orders 6 ខែចុងក្រោយ)
        // ==========================================
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        // ទាញទិន្នន័យ Group តាមខែ
        $monthlyStats = Order::select(
            DB::raw('SUM(grand_total) as revenue'), // 🌟 កែមកប្រើ grand_total
            DB::raw('COUNT(id) as orders_count'),
            DB::raw('MONTH(created_at) as month_num'),
            DB::raw('DATE_FORMAT(created_at, "%b") as month_name')
        )
            ->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy('month_name', 'month_num')
            ->orderBy('month_num')
            ->get();

        $chartData = [
            'labels'  => [],
            'revenue' => [],
            'orders'  => []
        ];

        foreach ($monthlyStats as $stat) {
            $chartData['labels'][]  = $stat->month_name;
            $chartData['revenue'][] = (float) $stat->revenue;
            $chartData['orders'][]  = (int) $stat->orders_count;
        }

        // ==========================================
        // ៣. Sales Activities (Recent Orders & Top Selling)
        // ==========================================

        $recentOrdersRaw = Order::with('user')->latest()->take(4)->get();

        $recentOrders = $recentOrdersRaw->map(function ($order) {
            return [
                // បំប្លែង ID ទៅជាទម្រង់ខ្លីងាយមើល បើកូដមាន length វែងពេក 
                // ឬអាចប្រើ $order->order_number ក៏បានព្រោះលោកអ្នកមាន Field នេះ
                'id'       => $order->order_number, // 🌟 ប្រើ order_number ដែលមានស្រាប់
                'customer' => $order->user ? $order->user->name : $order->shipping_name, // 🌟 បើគ្មាន user យកឈ្មោះ shipping
                'total'    => $order->grand_total, // 🌟 កែមកប្រើ grand_total
                'status'   => strtoupper($order->status),
                'date'     => $order->created_at->diffForHumans(),
            ];
        });

        // ទាញយកទំនិញលក់ដាច់ជាងគេ ៤ មុខ
        $topSellingProducts = Product::withSum('orderItems', 'quantity')
            ->with('thumbnail')
            ->having('order_items_sum_quantity', '>', 0)
            ->orderByDesc('order_items_sum_quantity')
            ->take(4)
            ->get()
            ->map(function ($product) {
                $product->sold_qty = $product->order_items_sum_quantity;
                return $product;
            });

        $salesActivities = [
            'recent_orders'        => $recentOrders,
            'top_selling_products' => $topSellingProducts
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
            'low_stock'    => $productsWithStock->where('current_stock', '>', 0)->take(4)->values(),
            'out_of_stock' => $productsWithStock->where('current_stock', '<=', 0)->take(4)->values(),
        ];

        $recentCustomersRaw = User::where('role', 'customer')->latest()->take(3)->get();
        $recentCustomers = $recentCustomersRaw->map(function ($user) {
            return [
                'name'   => $user->name,
                'email'  => $user->email,
                'joined' => $user->created_at->diffForHumans(),
            ];
        });

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
