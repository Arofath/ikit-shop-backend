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
        // ១. ចាប់យក Filter Parameter ពី Request
        // ==========================================
        // បើគ្មានគេបញ្ជូនមកទេ យើងយក 'last_6_months' ជា Default
        $range = $request->query('range', 'last_6_months');

        $startDate = Carbon::now();
        $endDate = Carbon::now();
        $groupBy = 'month'; // ជម្រើសគឺ 'month' ឬ 'day'

        // កំណត់ថ្ងៃចាប់ផ្តើម និងថ្ងៃបញ្ចប់ ទៅតាមប្រភេទ Filter
        switch ($range) {
            case 'last_7_days':
                $startDate = Carbon::now()->subDays(6)->startOfDay(); // រាប់បញ្ច្រាស ៦ថ្ងៃ + ថ្ងៃនេះ = ៧ថ្ងៃ
                $endDate = Carbon::now()->endOfDay();
                $groupBy = 'day';
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfDay();
                $groupBy = 'day';
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                $groupBy = 'day';
                break;
            case 'this_year':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfDay();
                $groupBy = 'month';
                break;
            case 'last_6_months':
            default:
                $startDate = Carbon::now()->subMonths(5)->startOfMonth();
                $endDate = Carbon::now()->endOfDay();
                $groupBy = 'month';
                break;
        }

        // ==========================================
        // ២. Query ទិន្នន័យ Chart តាមថ្ងៃ ឬ ខែ
        // ==========================================
        // កំណត់ទម្រង់ Group ក្នុង SQL ឱ្យត្រូវតាមលក្ខខណ្ឌ
        if ($groupBy === 'month') {
            $selectRaw = [
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('COUNT(id) as orders_count'),
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as group_key') // ឧ. 2024-05
            ];
        } else {
            $selectRaw = [
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('COUNT(id) as orders_count'),
                DB::raw('DATE(created_at) as group_key') // ឧ. 2024-05-15
            ];
        }

        // ទាញយក និងចងក្រង (Group) ទិន្នន័យ
        $stats = Order::select($selectRaw)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('payment_status', 'PAID') // រាប់តែ Order ដែលបានបង់ប្រាក់រួច
            ->groupBy('group_key')
            ->get()
            ->keyBy('group_key'); // ធ្វើឱ្យងាយស្រួលទាញយកតាម Key

        $labels = [];
        $revenue = [];
        $orders = [];

        // 🌟 Loop បង្កើត Labels និងបញ្ចូលទិន្នន័យ (ទោះខែ/ថ្ងៃនោះអត់លក់ដាច់ក៏ដោយ ក៏ដាក់ 0 ដែរ)
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            if ($groupBy === 'month') {
                $key = $currentDate->format('Y-m'); // ប្រើសម្រាប់ Match ជាមួយ Database
                $labels[] = $currentDate->format('M'); // លោតជាអក្សរ Jan, Feb... នៅលើ Chart
                $currentDate->addMonth();
            } else {
                $key = $currentDate->format('Y-m-d');
                $labels[] = $currentDate->format('d M'); // លោតជាអក្សរ 15 May... នៅលើ Chart
                $currentDate->addDay();
            }

            // ឆែកមើលថាតើ Key នេះមានទិន្នន័យក្នុង DB ដែរឬទេ?
            $stat = $stats->get($key);
            $revenue[] = $stat ? (float) $stat->revenue : 0;
            $orders[] = $stat ? (int) $stat->orders_count : 0;
        }

        $chartData = [
            'labels'  => $labels,
            'revenue' => $revenue,
            'orders'  => $orders
        ];

        // ==========================================
        // ៣. KPIs (Summary Cards) - អាចធ្វើតាម Filter ដែរ
        // ==========================================
        $summary = [
            // កន្លែងនេះបើយើងចង់ឱ្យកាតស្ថិតិខាងលើលោតលេខតាម Filter ដែរ អាចប្រើ whereBetween បែបនេះ៖
            'total_revenue'   => Order::whereBetween('created_at', [$startDate, $endDate])->where('payment_status', 'PAID')->sum('grand_total'),
            'total_orders'    => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
            'active_customers' => User::where('role', 'customer')->count(), // Customer សរុបមិនបាច់ Filter ទេ
            'pending_orders'  => Order::whereBetween('created_at', [$startDate, $endDate])->where('status', 'PENDING')->count(),
            'total_products'  => Product::count(), // មិនបាច់ Filter
        ];

        // ==========================================
        // ៤. Sales Activities & Alerts (រក្សាដូចដើម)
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
                'recent_customers' => $recentCustomers,
                'current_range'    => $range // បោះ Parameter ត្រឡប់ទៅប្រាប់ Frontend វិញថាវាកំពុងបង្ហាញទិន្នន័យអ្វី
            ]
        ]);
    }
}
