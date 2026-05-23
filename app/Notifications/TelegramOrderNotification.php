<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $order;

    /**
     * ទទួលយកទិន្នន័យ Order ពី Controller
     */
    public function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * កំណត់ប្រភេទទូរគមនាគមន៍ (Channel) ជា Telegram
     */
    public function via($notifiable)
    {
        return ['telegram'];
    }

    /**
     * រៀបចំទម្រង់សារដែលត្រូវបញ្ជូនទៅ Telegram Group
     */
    public function toTelegram($notifiable)
    {
        // 🌟 កំណត់ Link ទៅកាន់ Admin Panel ដោយប្រើ ADMIN_FRONTEND_URL ពី .env
        $adminOrderUrl = env('ADMIN_FRONTEND_URL', 'http://localhost:5173') . '/admin/orders/' . $this->order->id;

        // ត្រួតពិនិត្យមើលស្ថានភាពបង់ប្រាក់
        $paymentStatus = $this->order->payment_status === 'PAID' ? '✅ បានបង់ប្រាក់' : '⏳ រង់ចាំការបង់ប្រាក់';

        return TelegramMessage::create()
            // កំណត់ Chat ID ទីដៅ (Group) ដោយទាញពី .env
            ->to(env('TELEGRAM_CHAT_ID'))

            // រៀបចំអត្ថបទ (Markdown V2 Format)
            ->content("*🛒 មានការបញ្ជាទិញថ្មី (New Order)!*\n\n")
            ->line("*លេខកូដ (Order ID):* `#" . $this->order->order_number . "`")
            ->line("*អតិថិជន (Customer):* " . ($this->order->shipping_name ?? 'Unknown'))
            ->line("*លេខទូរស័ព្ទ (Phone):* " . ($this->order->shipping_phone ?? 'N/A'))
            ->line("*ទឹកប្រាក់សរុប (Total):* `$" . number_format($this->order->total_amount, 2) . "`")
            ->line("*ការបង់ប្រាក់ (Payment):* " . $paymentStatus)

            // 🌟 បន្ថែមប៊ូតុងនៅខាងក្រោមសារ ដែល Link ទៅកាន់ Admin Dashboard (Port 5173)
            ->button('👉 មើលព័ត៌មានលម្អិត (View Details)', $adminOrderUrl);
    }
}
