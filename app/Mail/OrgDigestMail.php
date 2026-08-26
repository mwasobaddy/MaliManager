<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Weekly portfolio digest: deterministic numbers plus an optional AI
 * narrative paragraph (when the organization has its own key).
 */
class OrgDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, int|float>  $numbers
     */
    public function __construct(
        public Organization $organization,
        public array $numbers,
        public ?string $narrative,
        public ?string $reportsUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your weekly MaliManager digest — {$this->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.org-digest',
        );
    }
}
