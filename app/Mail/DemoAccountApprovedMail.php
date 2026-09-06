<?php

namespace App\Mail;

use App\Models\DemoRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoAccountApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DemoRequest $demoRequest, public string $password) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre démonstration Easy School est prête');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.demo.approved');
    }
}
