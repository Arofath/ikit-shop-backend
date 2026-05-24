<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramOrderNotification extends Notification
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
        $adminOrderUrl = env('ADMIN_FRONTEND_URL', 'http://localhost:5173') . '/admin/orders/' . $this->order->id;
        $paymentStatus = $this->order->payment_status === 'PAID' ? '✅ បានបង់ប្រាក់' : '⏳ រង់ចាំការបង់ប្រាក់';

        $telegramMessage = TelegramMessage::create()
            ->to(env('TELEGRAM_CHAT_ID'))
            ->content("*🛒 មានការបញ្ជាទិញថ្មី (New Order)!*\n\n")
            ->line("*លេខកូដ (Order ID):* `#" . $this->order->order_number . "`")
            ->line("*អតិថិជន (Customer):* " . ($this->order->shipping_name ?? 'Unknown'))
            ->line("*លេខទូរស័ព្ទ (Phone):* " . ($this->order->shipping_phone ?? 'N/A'))
            ->line("*ទឹកប្រាក់សរុប (Total):* `$" . number_format($this->order->total_amount, 2) . "`")
            ->line("*ការបង់ប្រាក់ (Payment):* " . $paymentStatus);

        // 🌟 ដោះស្រាយបញ្ហា Localhost
        // បើ URL មានពាក្យ localhost យើងគ្រាន់តែបង្ហាញជាអក្សរធម្មតា
        if (str_contains($adminOrderUrl, 'localhost')) {
            $telegramMessage->line("\n🔗 *Link សម្រាប់ Admin:* \n`" . $adminOrderUrl . "`");
        } else {
            // បើវាជា Domain ពិតប្រាកដនៅលើ Hosting ទើបយើងបង្ហាញជាប៊ូតុង
            $telegramMessage->button('👉 មើលព័ត៌មានលម្អិត (View Details)', $adminOrderUrl);
        }

        return $telegramMessage;
    }
}
