<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramPaymentSuccessNotification extends Notification
{
    use Queueable;

    public $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['telegram'];
    }

    public function toTelegram($notifiable)
    {
        $adminOrderUrl = env('ADMIN_FRONTEND_URL', 'http://localhost:5173') . '/admin/orders/' . $this->order->id;

        // 🌟 ១. រៀបចំបញ្ជីទំនិញ (Items Summary) យកចេញពីគំរូដើម
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

        // 🌟 ២. សាងសង់សារ Telegram សម្រាប់ពេលលុយចូល
        $telegramMessage = TelegramMessage::create()
            ->to(env('TELEGRAM_CHAT_ID'))
            ->content("*🎉 ទទួលបានការទូទាត់ប្រាក់ជោគជ័យ (KHQR)!*\n\n")

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
            ->line("*សេវាដឹកជញ្ជូន:* `$" . number_format($this->order->shipping_fee, 2) . "`")
            ->line("*សរុបទាំងអស់ (Grand Total):* `$" . number_format($this->order->grand_total, 2) . "`")
            ->line("*លេខប្រតិបត្តិការ (TXN):* `" . ($this->order->payment->transaction_hash ?? 'N/A') . "`")
            ->line("*ស្ថានភាព:* ✅ បង់ប្រាក់រួចរាល់ (PAID)")
            ->line("\n_សូមរៀបចំទំនិញសម្រាប់អតិថិជន!_");

        // 🌟 ៣. (ជម្រើស) បើចង់បង្ហាញ Link ទៅកាន់ Admin អាចបើកកូដនេះបាន
        /*
        if (str_contains($adminOrderUrl, 'localhost')) {
            $telegramMessage->line("\n🔗 *Link សម្រាប់ Admin:* \n`" . $adminOrderUrl . "`");
        } else {
            $telegramMessage->button('👉 ចុចទីនេះដើម្បីចាត់ចែង (View Order)', $adminOrderUrl);
        }
        */

        return $telegramMessage;
    }
}
