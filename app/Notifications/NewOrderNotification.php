<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Order;

class NewOrderNotification extends Notification
{
    use Queueable;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    // ប្រាប់ Laravel ឱ្យរក្សាទុកទិន្នន័យនេះទៅក្នុង Database (Table: notifications)
    public function via($notifiable)
    {
        return ['database'];
    }

    // ទិន្នន័យដែលត្រូវរក្សាទុក ដើម្បីឱ្យ Frontend យកទៅបង្ហាញ
    public function toDatabase($notifiable)
    {
        return [
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'amount'       => $this->order->grand_total,
            'message'      => "New order {$this->order->order_number} has been placed.",
            'type'         => 'new_order'
        ];
    }
}
