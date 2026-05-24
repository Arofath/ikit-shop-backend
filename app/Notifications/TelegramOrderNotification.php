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

        // 🌟 ១. រៀបចំ Payment 
        $paymentStatus = $this->order->payment_status === 'PAID' ? '✅ PAID' : '⏳ UNPAID';
        $paymentMethod = str_replace('_', ' ', $this->order->payment_method); // ប្តូរ CASH_ON_DELIVERY ទៅ CASH ON DELIVERY

        // 🌟 ២. រៀបចំបញ្ជីទំនិញ (Items Summary)
        $itemsList = "";
        if ($this->order->items && $this->order->items->count() > 0) {
            foreach ($this->order->items as $item) {
                // កាត់ឈ្មោះទំនិញកុំឱ្យវែងពេក (ត្រឹម 30 អក្សរ)
                $productName = mb_strimwidth($item->product_name, 0, 30, '...');
                $itemsList .= "• {$item->quantity}x {$productName} \n";
            }
        } else {
            $itemsList = "• មិនមានព័ត៌មានទំនិញ\n";
        }

        // 🌟 ៣. ចាប់ផ្តើមសាងសង់សារ Telegram
        $telegramMessage = TelegramMessage::create()
            ->to(env('TELEGRAM_CHAT_ID'))
            ->content("*🛒 មានការបញ្ជាទិញថ្មីពី វេបសាយ!*\n\n")

            // ផ្នែកព័ត៌មានទូទៅ
            ->line("*លេខកូដ (Order ID):* `#" . $this->order->order_number . "`")
            ->line("*អតិថិជន (Customer):* " . ($this->order->shipping_name ?? 'Unknown'))
            ->line("*លេខទូរស័ព្ទ (Phone):* `" . ($this->order->shipping_phone ?? 'N/A') . "`")
            ->line("*អាសយដ្ឋាន (Address):* " . ($this->order->shipping_address ?? 'N/A'))
            ->line("")

            // ផ្នែកទំនិញ (លោតចុះបន្ទាត់ស្អាត)
            ->line("*📦 ទំនិញដែលបានកម្ម៉ង់ (Items):*")
            ->line($itemsList)
            ->line("")

            // ផ្នែកហិរញ្ញវត្ថុ
            ->line("*វិធីបង់ប្រាក់:* " . $paymentMethod)
            ->line("*សេវាដឹកជញ្ជូន:* `$" . number_format($this->order->shipping_fee, 2) . "`")
            ->line("*សរុបទាំងអស់ (Grand Total):* `$" . number_format($this->order->grand_total, 2) . "`")
            ->line("*ស្ថានភាព (Status):* " . $paymentStatus);

        // 🌟 ៤. ដោះស្រាយបញ្ហា URL សម្រាប់ប៊ូតុង
        if (str_contains($adminOrderUrl, 'localhost')) {
            $telegramMessage->line("\n🔗 *Link សម្រាប់ Admin:* \n`" . $adminOrderUrl . "`");
        } else {
            $telegramMessage->button('👉 ចុចទីនេះដើម្បីចាត់ចែង (View Order)', $adminOrderUrl);
        }

        return $telegramMessage;
    }
}
