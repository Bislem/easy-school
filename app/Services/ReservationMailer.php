<?php

namespace App\Services;

use App\Mail\ReservationUpdateMail;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;

class ReservationMailer
{
    public function send(Reservation $reservation, string $subject, string $message): void
    {
        $reservation->loadMissing(['user', 'car', 'secondaryDriver', 'payments', 'fuelTankRecords.recordedBy']);

        Mail::to($reservation->user)->send(new ReservationUpdateMail(
            reservation: $reservation,
            subjectLine: $subject,
            messageText: $message,
        ));
    }
}
