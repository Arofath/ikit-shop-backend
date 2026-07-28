<?php

namespace App\Exports;

use App\Models\Order;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $type;

    public function __construct($type)
    {
        $this->type = $type;
    }

    public function collection()
    {
        $startDate = Carbon::today();
        $endDate = Carbon::today()->endOfDay();

        if ($this->type === 'monthly') {
            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();
        } elseif ($this->type === 'yearly') {
            $startDate = Carbon::now()->startOfYear();
            $endDate = Carbon::now()->endOfYear();
        }

        // ទាញយក Order ទាំងអស់ក្នុងចន្លោះពេលកំណត់
        return Order::with('customer')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // រៀបចំក្បាល Column ក្នុង Excel
    public function headings(): array
    {
        return ['Date', 'Order No', 'Customer Name', 'Discount ($)', 'Grand Total ($)', 'Payment Status', 'Order Status'];
    }

    // រៀបចំទិន្នន័យចូលតាម Column នីមួយៗ
    public function map($order): array
    {
        return [
            $order->created_at->format('d M Y, H:i'),
            $order->order_number,
            $order->customer ? $order->customer->name : ($order->shipping_name ?? 'Walk-in Customer'),
            $order->discount_percent,
            $order->grand_total,
            $order->payment_status,
            $order->status,
        ];
    }
}
