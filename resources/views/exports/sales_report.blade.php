<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; padding: 0; }
        .summary-box { border: 1px solid #ddd; padding: 10px; margin-bottom: 20px; width: 100%; border-collapse: collapse; }
        .summary-box td { padding: 10px; text-align: center; font-size: 14px; font-weight: bold; border: 1px solid #ddd; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table.data-table th { background-color: #f4f4f4; }
        .text-right { text-align: right !important; }
    </style>
</head>
<body>

    <div class="header">
        <h2>Sales Report ({{ ucfirst($type) }})</h2>
        <p>Generated on: {{ \Carbon\Carbon::now()->format('d M Y, H:i') }}</p>
    </div>

    <!-- សេចក្តីសង្ខេប KPIs -->
    <table class="summary-box">
        <tr>
            <td>Total Orders: <br><span style="color: #2563eb;">{{ $totalOrders }}</span></td>
            <td>Total Revenue: <br><span style="color: #16a34a;">${{ number_format($totalRevenue, 2) }}</span></td>
            <td>Total Discounts: <br><span style="color: #9333ea;">${{ number_format($totalDiscounts, 2) }}</span></td>
        </tr>
    </table>

    <!-- តារាងទិន្នន័យលម្អិត -->
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Order No</th>
                <th>Customer</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td>{{ $order->created_at->format('d M Y') }}</td>
                <td>{{ $order->order_number }}</td>
                <td>{{ $order->user ? $order->user->name : ($order->shipping_name ?? 'Walk-in') }}</td>
                <td class="text-right">${{ number_format($order->discount_total, 2) }}</td>
                <td class="text-right">${{ number_format($order->grand_total, 2) }}</td>
                <td>{{ $order->payment_status }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center;">No completed orders found for this period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>