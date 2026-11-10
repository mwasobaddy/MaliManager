<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $organizationName,
        public string $planName,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Billing reminder for {$this->organizationName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.billing-reminder',
            text: 'email.billing-reminder-text',
        );
    }
}
