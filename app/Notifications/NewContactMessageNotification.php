<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Contact;

class NewContactMessageNotification extends Notification
{
    use Queueable;

    public $contact;

    public function __construct(Contact $contact)
    {
        $this->contact = $contact;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'contact_id'   => $this->contact->id,
            'contact_name' => $this->contact->name,
            'subject'      => $this->contact->subject,
            'message'      => "New contact message from {$this->contact->name}: {$this->contact->subject}",
            'type'         => 'new_contact'
        ];
    }
}
