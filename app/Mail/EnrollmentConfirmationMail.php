<?php

namespace App\Mail;

use App\Models\CourseEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EnrollmentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CourseEnrollment $enrollment, public string $confirmationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Confirmez votre inscription à la formation");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.enrollments.confirmation');
    }
}
