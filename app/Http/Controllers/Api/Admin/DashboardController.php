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
        // ១. ចាប់យក Filter Parameters ទាំង ២ ដាច់ពីគ្នា
        // ==========================================
        $cardRange = $request->query('card_range', 'this_month'); // Default សម្រាប់ Card គឺ 'this_month'
        $chartRange = $request->query('chart_range', 'last_6_months'); // Default សម្រាប់ Chart គឺ 'last_6_months'

        // ទាញយកថ្ងៃចាប់ផ្តើម និងថ្ងៃបញ្ចប់ ពីអនុគមន៍ដែលយើងបានបង្កើតនៅខាងក្រោម
        [$cardStart, $cardEnd] = $this->getDatesFromRange($cardRange);
        [$chartStart, $chartEnd, $groupBy] = $this->getDatesFromRange($chartRange);

        // ==========================================
        // ២. KPIs (Summary Cards) - គិតលេខតាម $cardRange
        // ==========================================
        $summary = [
            'total_revenue'   => Order::whereBetween('created_at', [$cardStart, $cardEnd])->where('payment_status', 'PAID')->sum('grand_total'),
            'total_orders'    => Order::whereBetween('created_at', [$cardStart, $cardEnd])->count(),
            'active_customers' => User::where('role', 'customer')->count(), // Customer មិនបាច់ Filter ទេ
            'pending_orders'  => Order::whereBetween('created_at', [$cardStart, $cardEnd])->where('status', 'PENDING')->count(),
            'total_products'  => Product::count(), // Product មិនបាច់ Filter ទេ
        ];

        // ==========================================
        // ៣. Chart Data (Revenue & Orders) - គិតលេខតាម $chartRange
        // ==========================================
        if ($groupBy === 'month') {
            $selectRaw = [
                DB::raw('SUM(CASE WHEN payment_status = "PAID" THEN grand_total ELSE 0 END) as revenue'),
                DB::raw('COUNT(id) as orders_count'),
                // 🌟 ថែមការរាប់តែ Order ដែលបានបង់ប្រាក់
                DB::raw('SUM(CASE WHEN payment_status = "PAID" THEN 1 ELSE 0 END) as paid_orders_count'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as group_key')
            ];
        } else {
            $selectRaw = [
                DB::raw('SUM(CASE WHEN payment_status = "PAID" THEN grand_total ELSE 0 END) as revenue'),
                DB::raw('COUNT(id) as orders_count'),
                // 🌟 ថែមការរាប់តែ Order ដែលបានបង់ប្រាក់
                DB::raw('SUM(CASE WHEN payment_status = "PAID" THEN 1 ELSE 0 END) as paid_orders_count'),
                DB::raw('DATE(created_at) as group_key')
            ];
        }

        $stats = Order::select($selectRaw)
            ->whereBetween('created_at', [$chartStart, $chartEnd])
            ->groupBy('group_key')
            ->get()
            ->keyBy('group_key');

        $labels = [];
        $revenue = [];
        $orders = [];
        $paidOrders = [];

        $currentDate = $chartStart->copy();
        while ($currentDate <= $chartEnd) {
            if ($groupBy === 'month') {
                $key = $currentDate->format('Y-m');
                $labels[] = $currentDate->format('M Y'); // ឧ. May 2024
                $currentDate->addMonth();
            } else {
                $key = $currentDate->format('Y-m-d');
                $labels[] = $currentDate->format('d M'); // ឧ. 15 May
                $currentDate->addDay();
            }

            $stat = $stats->get($key);
            $revenue[] = $stat ? (float) $stat->revenue : 0;
            $orders[] = $stat ? (int) $stat->orders_count : 0;
            $paidOrders[] = $stat ? (int) $stat->paid_orders_count : 0;
        }

        $chartData = [
            'labels'  => $labels,
            'revenue' => $revenue,
            'orders'  => $orders,
            'paid_orders' => $paidOrders
        ];

        // ==========================================
        // ៤. Sales Activities & Alerts (រក្សាទុកដដែល)
        // ==========================================
        $recentOrdersRaw = Order::with('user')->latest()->take(4)->get();
        $recentOrders = $recentOrdersRaw->map(function ($order) {
            return [
                'id'       => $order->order_number,
                'customer' => $order->user ? $order->user->name : $order->shipping_name,
                'total'    => $order->grand_total,
                'status'   => strtoupper($order->status),
                'date'     => $order->created_at->diffForHumans(),
            ];
        });

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
        // ៥. បញ្ជូនទិន្នន័យទៅ Frontend
        // ==========================================
        return response()->json([
            'success' => true,
            'message' => 'E-commerce Dashboard data retrieved successfully.',
            'data' => [
                'summary'          => $summary,
                'chart_data'       => $chartData,
                'sales_activities' => $salesActivities,
                'alerts'           => $alerts,
                'recent_customers' => $recentCustomers,
                'current_filters'  => [
                    'card_range'  => $cardRange,
                    'chart_range' => $chartRange
                ]
            ]
        ]);
    }

    /**
     * អនុគមន៍ជំនួយ (Helper) សម្រាប់គណនាថ្ងៃខែ ផ្អែកតាម Range
     */
    private function getDatesFromRange($range)
    {
        $now = Carbon::now();

        switch ($range) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'day'];
            case 'yesterday':
                return [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'day'];
            case 'last_7_days':
                return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'day'];
            case 'last_month':
                return [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth(), 'day'];
            case 'this_year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfDay(), 'month'];
            case 'last_6_months':
                return [$now->copy()->subMonths(5)->startOfMonth(), $now->copy()->endOfDay(), 'month'];
            case 'this_month':
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'day'];
        }
    }
}
