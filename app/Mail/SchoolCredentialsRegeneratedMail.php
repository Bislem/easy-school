<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SchoolCredentialsRegeneratedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $school,
        public User $administrator,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vos nouveaux identifiants Easy School');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.school.credentials-regenerated');
    }
}
