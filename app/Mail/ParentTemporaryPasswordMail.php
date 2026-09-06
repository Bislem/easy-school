<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParentTemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $parent, public string $temporaryPassword, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->reason === 'account_created' ? 'Votre compte parent Easy School' : 'Réinitialisation de votre mot de passe Easy School');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.parent.temporary-password');
    }
}
