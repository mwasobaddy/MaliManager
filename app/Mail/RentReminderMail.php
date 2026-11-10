<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $propertyName,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rent reminder'.($this->propertyName !== '' ? " for {$this->propertyName}" : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.rent-reminder',
            text: 'email.rent-reminder-text',
        );
    }
}
