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
        // 🌟 ១. ចាប់យក User បច្ចុប្បន្ន និងឆែកមើលតួនាទី 
        $user = $request->user();
        $isSaleStaff = $user->role === 'sale_staff';

        // ==========================================
        // 🌟 ២. ចាប់យក និងត្រួតពិនិត្យ Filter Parameters ផ្អែកលើ Role
        // ==========================================
        $requestedCardRange = $request->query('card_range', 'today'); // លំនាំដើម today
        $requestedChartRange = $request->query('chart_range', 'this_month');

        if ($isSaleStaff) {
            // កំណត់សិទ្ធិសម្រាប់ Sale Staff
            $allowedStaffCardRanges = ['today', 'yesterday', 'last_7_days', 'this_month'];
            $cardRange = in_array($requestedCardRange, $allowedStaffCardRanges) ? $requestedCardRange : 'today';

            $allowedStaffChartRanges = ['last_7_days', 'this_month'];
            $chartRange = in_array($requestedChartRange, $allowedStaffChartRanges) ? $requestedChartRange : 'this_month';
        } else {
            // កំណត់សិទ្ធិសម្រាប់ Admin
            $allowedAdminCardRanges = ['today', 'yesterday', 'last_7_days', 'this_month', 'this_year'];
            $cardRange = in_array($requestedCardRange, $allowedAdminCardRanges) ? $requestedCardRange : 'today';

            $allowedAdminChartRanges = ['last_7_days', 'this_month', 'last_6_months', 'this_year'];
            $chartRange = in_array($requestedChartRange, $allowedAdminChartRanges) ? $requestedChartRange : 'last_6_months';
        }

        [$cardStart, $cardEnd] = $this->getDatesFromRange($cardRange);
        [$chartStart, $chartEnd, $groupBy] = $this->getDatesFromRange($chartRange);

        // ==========================================
        // ៣. KPIs (Summary Cards) - គិតលេខតាម $cardRange 
        // ==========================================
        $summary = [
            'total_revenue'   => Order::whereHas('payment', function ($q) use ($cardStart, $cardEnd) {
                $q->where('status', 'COMPLETED')
                    ->whereBetween('paid_at', [$cardStart, $cardEnd]);
            })
                ->where('payment_status', 'PAID')
                ->sum('grand_total'),

            'total_orders'    => Order::whereBetween('created_at', [$cardStart, $cardEnd])->count(),
            'active_customers' => User::where('role', 'customer')->count(),
            'pending_orders'  => Order::whereBetween('created_at', [$cardStart, $cardEnd])->where('status', 'PENDING')->count(),
            'total_products'  => Product::count(),
        ];

        // ==========================================
        // ៤. Chart Data (Revenue & Orders) - គិតលេខតាម $chartRange 
        // ==========================================
        $groupFormat = $groupBy === 'month' ? '"%Y-%m"' : 'DATE(%s)';
        $mysqlFormat = $groupBy === 'month' ? 'DATE_FORMAT(%s, "%%Y-%%m")' : 'DATE(%s)';

        // ៤.១ ទាញទិន្នន័យចំនួន Order 
        $ordersStats = Order::select([
            DB::raw('COUNT(id) as orders_count'),
            DB::raw('SUM(CASE WHEN payment_status = "PAID" THEN 1 ELSE 0 END) as paid_orders_count'),
            DB::raw(sprintf($mysqlFormat, 'created_at') . ' as group_key')
        ])
            ->whereBetween('created_at', [$chartStart, $chartEnd])
            ->groupBy('group_key')
            ->get()
            ->keyBy('group_key');

        // ៤.២ ទាញទិន្នន័យចំណូល (ធ្វើការ Query តែនៅពេលដែល User មិនមែនជា Sale Staff) 
        $revenueStats = collect();

        if (!$isSaleStaff) {
            $revenueStats = Order::select([
                DB::raw('SUM(orders.grand_total) as revenue'),
                DB::raw(sprintf($mysqlFormat, 'payments.paid_at') . ' as group_key')
            ])
                ->join('payments', 'orders.id', '=', 'payments.order_id')
                ->where('orders.payment_status', 'PAID')
                ->where('payments.status', 'COMPLETED')
                ->whereNotNull('payments.paid_at')
                ->whereBetween('payments.paid_at', [$chartStart, $chartEnd])
                ->groupBy('group_key')
                ->get()
                ->keyBy('group_key');
        }

        $labels = [];
        $revenue = [];
        $orders = [];
        $paidOrders = [];

        $currentDate = $chartStart->copy();
        while ($currentDate <= $chartEnd) {
            if ($groupBy === 'month') {
                $key = $currentDate->format('Y-m');
                $labels[] = $currentDate->format('M Y');
                $currentDate->addMonth();
            } else {
                $key = $currentDate->format('Y-m-d');
                $labels[] = $currentDate->format('d M');
                $currentDate->addDay();
            }

            $orderStat = $ordersStats->get($key);
            $revStat = $revenueStats->get($key);

            // 🌟 តម្លៃ Revenue នឹងក្លាយជា 0 ដោយស្វ័យប្រវត្តិសម្រាប់ Sale Staff 
            $revenue[] = $revStat ? (float) $revStat->revenue : 0;
            $orders[] = $orderStat ? (int) $orderStat->orders_count : 0;
            $paidOrders[] = $orderStat ? (int) $orderStat->paid_orders_count : 0;
        }

        $chartData = [
            'labels'  => $labels,
            'revenue' => $revenue,
            'orders'  => $orders,
            'paid_orders' => $paidOrders
        ];

        // ==========================================
        // ៥. Sales Activities & Alerts 
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
        // ៦. បញ្ជូនទិន្នន័យទៅ Frontend 
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
