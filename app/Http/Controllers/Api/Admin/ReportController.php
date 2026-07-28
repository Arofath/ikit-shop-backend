<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\SalesReportExport;            
use Maatwebsite\Excel\Facades\Excel;          
use Barryvdh\DomPDF\Facade\Pdf;               

class ReportController extends Controller
{
    /**
     * ទាញយកទិន្នន័យរបាយការណ៍ និង KPIs សម្រាប់បង្ហាញលើ UI
     */
    public function index(Request $request)
    {
        // ១. ចាប់យកប្រភេទ Filter ពី Frontend (daily, monthly, yearly)
        $type = $request->query('type', 'daily');

        // ២. កំណត់ចន្លោះពេល (Date Range) ដោយផ្អែកលើប្រភេទ Filter
        $startDate = Carbon::today();
        $endDate = Carbon::today()->endOfDay();

        if ($type === 'monthly') {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
        } elseif ($type === 'yearly') {
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now()->endOfYear();
        }

        // ==========================================
        // ផ្នែកទី ១៖ គណនាទិន្នន័យ KPIs ទាំង ៤ (យកតែ Order ដែលបានបង់ប្រាក់)
        // ==========================================

        // យើងប្រើ Clone ដើម្បីកុំឱ្យ Query ជាន់គ្នា
        $kpiQuery = Order::where('payment_status', 'PAID')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalRevenue = (clone $kpiQuery)->sum('grand_total');
        $totalOrders = (clone $kpiQuery)->count();
        $totalDiscounts = (clone $kpiQuery)->sum('discount');

        // រាប់ចំនួនទំនិញលក់ចេញសរុប (Products Sold) ដោយបូកបញ្ជូល quantity ពី Table order_items
        $productsSold = (clone $kpiQuery)->withSum('items', 'quantity')->get()->sum('items_sum_quantity');

        // ==========================================
        // ផ្នែកទី ២៖ ទាញយកបញ្ជីវិក្កយបត្រ សម្រាប់តារាង (Table)
        // ==========================================

        $orders = Order::with(['user' => function ($query) {
            $query->select('id', 'name');
        }])
            ->where('payment_status', 'PAID')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $formattedOrders = $orders->map(function ($order) {
            return [
                'id'             => $order->id,
                'date'           => $order->created_at->format('d M Y'),
                'order_number'   => $order->order_number,

                // 🌟 ដូរពី $order->customer ទៅជា $order->user វិញនៅទីនេះ
                'customer_name'  => $order->user ? $order->user->name : ($order->shipping_name ?? 'Walk-in Customer'),

                'discount'       => (float) $order->discount,
                'amount'         => (float) $order->grand_total,
                'payment_status' => $order->payment_status,
            ];
        });

        // ៣. បញ្ជូនទិន្នន័យទៅកាន់ Vue.js វិញ
        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'total_revenue'   => $totalRevenue,
                    'total_orders'    => $totalOrders,
                    'products_sold'   => $productsSold,
                    'total_discounts' => $totalDiscounts,
                ],
                'orders' => $formattedOrders,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                    'total'        => $orders->total(),
                ]
            ]
        ]);
    }

    /**
     * Export ទិន្នន័យទៅជា Excel
     */
    public function exportExcel(Request $request)
    {
        $type = $request->query('type', 'daily');
        $fileName = "sales_report_{$type}_" . now()->format('Ymd') . ".xlsx";

        return Excel::download(new SalesReportExport($type), $fileName);
    }

    /**
     * Export ទិន្នន័យទៅជា PDF
     */
    public function exportPdf(Request $request)
    {
        $type = $request->query('type', 'daily');

        $startDate = Carbon::today();
        $endDate = Carbon::today()->endOfDay();

        if ($type === 'monthly') {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
        } elseif ($type === 'yearly') {
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now()->endOfYear();
        }

        $orders = Order::with('customer')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        // 🌟 ប្រើប្រាស់ View ឈ្មោះ exports.sales_report_pdf ដើម្បីគូរប្លង់ PDF
        $pdf = Pdf::loadView('exports.sales_report_pdf', [
            'orders' => $orders,
            'type'   => $type,
            'date'   => now()->format('d M Y')
        ]);

        return $pdf->download("sales_report_{$type}_" . now()->format('Ymd') . ".pdf");
    }
}
