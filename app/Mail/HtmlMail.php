<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Generic HTML mail — carries a pre-rendered subject + body so callers can
 * keep using database-driven templates (email_templates) exactly like the
 * legacy sendEmail() helper did.
 */
class HtmlMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $mailSubject,
        public string $mailHtmlBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->mailHtmlBody);
    }
}