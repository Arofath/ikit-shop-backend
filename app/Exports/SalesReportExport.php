<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        // ទាញយកតែ Order ដែលបានបង់ប្រាក់រួច (PAID)
        return Order::with('user')
            ->where('payment_status', 'PAID')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Date',
            'Order No',
            'Customer Name',
            'Discount Amount ($)',
            'Grand Total ($)',
            'Payment Status',
            'Order Status'
        ];
    }

    public function map($order): array
    {
        return [
            $order->created_at->format('d M Y, H:i'),
            $order->order_number,
            $order->user ? $order->user->name : ($order->shipping_name ?? 'Walk-in Customer'),
            $order->discount_total,
            $order->grand_total,
            $order->payment_status,
            $order->status,
        ];
    }

    // ធ្វើឱ្យជួរទី ១ (Headings) ជាអក្សរដិត (Bold)
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
