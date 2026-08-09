<?php

namespace App\Mail;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public string $subjectLine,
        public string $messageText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reservations.update', with: [
            'agency' => CompanySetting::current(),
        ]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => Pdf::loadView('admin.reservations.print', [
                    'reservation' => $this->reservation,
                    'statusMeta' => ReservationStatus::getMeta(),
                    'paymentStatusMeta' => PaymentStatus::getMeta(),
                    'currency' => config('app.currency_symbol'),
                    'agency' => CompanySetting::current(),
                ])->output(),
                $this->reservation->reservation_number . '-contract.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
