<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegisterOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otpCode; // បង្កើត Variable សម្រាប់ស្តុកទុក OTP Code

    public function __construct($otpCode)
    {
        $this->otpCode = $otpCode; // ទទួលយកទិន្នន័យពី Controller
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verification Code (OTP) for Registration',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.register_otp', // ចង្អុលទៅកាន់ Blade Template ដែលយើងនឹងបង្កើត
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
