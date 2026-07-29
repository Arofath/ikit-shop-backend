<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReceiptRejectedNotification extends Notification
{
    use Queueable;

    protected $order;
    protected $reason;

    /**
     * ទទួលយកទិន្នន័យ Order និងមូលហេតុពី Controller
     */
    public function __construct($order, $reason)
    {
        $this->order = $order;
        $this->reason = $reason;
    }

    /**
     * កំណត់ Channel ក្នុងការបញ្ជូន (យើងប្រើត្រឹម database សម្រាប់បង្ហាញក្នុង App/Web)
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * រៀបចំទម្រង់ទិន្នន័យដែលត្រូវរក្សាទុកក្នុង Table `notifications`
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'type' => 'RECEIPT_REJECTED',
            'title' => 'Payment Issue - ' . $this->order->order_number,
            'message' => 'Your payment receipt was rejected. Reason: ' . $this->reason,
        ];
    }
}
