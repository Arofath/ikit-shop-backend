<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Order;

class OrderStatusUpdatedNotification extends Notification
{
    use Queueable;

    public $order;
    public $statusType; // អាចជា 'status' (ការដឹកជញ្ជូន) ឬ 'payment' (ការបង់ប្រាក់)

    public function __construct(Order $order, $statusType = 'status')
    {
        $this->order = $order;
        $this->statusType = $statusType;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $statusValue = $this->statusType === 'payment' ? $this->order->payment_status : $this->order->status;
        $message = $this->statusType === 'payment'
            ? "Your payment status is now {$statusValue}."
            : "Your order status is now {$statusValue}.";

        return [
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'status'       => $statusValue,
            'message'      => $message,
            'type'         => 'status_update'
        ];
    }
}
