<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\Order; // 🌟 ទាញយក Model Order
use App\Models\User;  // 🌟 ទាញយក Model User
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
            // សរុបចំណូលពី Order ដែលបានបង់ប្រាក់រួច (PAID/COMPLETED)
            'total_revenue'   => Order::whereIn('status', ['PAID', 'COMPLETED'])->sum('total_amount'),

            // សរុបចំនួន Order ទាំងអស់
            'total_orders'    => Order::count(),

            // សរុបអតិថិជន (សន្មតថា user ដែលមាន role = 'customer' ឬ 'user')
            'active_customers' => User::where('role', 'customer')->count(),

            // Order ដែលកំពុងរង់ចាំ (PENDING)
            'pending_orders'  => Order::where('status', 'PENDING')->count(),

            'total_products'  => Product::count(),
        ];

        // ==========================================
        // ២. Chart Data (Revenue & Orders 6 ខែចុងក្រោយ)
        // ==========================================
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        // ទាញទិន្នន័យ Group តាមខែ
        $monthlyStats = Order::select(
            DB::raw('SUM(total_amount) as revenue'),
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

        // បញ្ចូលទិន្នន័យទៅក្នុង Array សម្រាប់ Chart
        foreach ($monthlyStats as $stat) {
            $chartData['labels'][]  = $stat->month_name;
            $chartData['revenue'][] = (float) $stat->revenue;
            $chartData['orders'][]  = (int) $stat->orders_count;
        }

        // ==========================================
        // ៣. Sales Activities (Recent Orders & Top Selling)
        // ==========================================

        // ទាញយក Order ថ្មីៗបំផុតចំនួន ៤ (ត្រូវមាន relationship 'user' ឬ 'customer' ក្នុង Order Model)
        $recentOrdersRaw = Order::with('user')->latest()->take(4)->get();

        $recentOrders = $recentOrdersRaw->map(function ($order) {
            return [
                'id'       => '#ORD-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                'customer' => $order->user ? $order->user->name : 'Guest',
                'total'    => $order->total_amount,
                'status'   => strtoupper($order->status),
                'date'     => $order->created_at->diffForHumans(), // លោតជា 2 mins ago...
            ];
        });

        // ទាញយកទំនិញលក់ដាច់ជាងគេ ៤ មុខ
        // 💡 ចំណាំ៖ តម្រូវឱ្យមាន Relationship `orderItems()` នៅក្នុង Product Model របស់អ្នក
        $topSellingProducts = Product::withSum('orderItems', 'quantity')
            ->with('thumbnail')
            ->having('order_items_sum_quantity', '>', 0)
            ->orderByDesc('order_items_sum_quantity')
            ->take(4)
            ->get()
            ->map(function ($product) {
                $product->sold_qty = $product->order_items_sum_quantity; // យកតម្លៃពិតមកដាក់
                return $product;
            });

        $salesActivities = [
            'recent_orders'        => $recentOrders,
            'top_selling_products' => $topSellingProducts
        ];

        // ==========================================
        // ៤. Secondary Info (Stock Alerts & Recent Customers)
        // ==========================================

        // Stock Alerts (រក្សាទុកកូដចាស់របស់អ្នកព្រោះវាដើរត្រូវហើយ)
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

        // ទាញយកអតិថិជនថ្មីៗ ៣ នាក់ចុងក្រោយ
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
