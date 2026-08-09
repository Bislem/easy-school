<?php

namespace App\Http\Controllers\Client;

use App\Enums\PaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Reservation;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class ReservationsController extends Controller
{
    public function __construct(private FilePondService $filePondService) {}

    public function index(Request $request)
    {

        $reservations = Reservation::where('user_id', auth()->user()->id)
            ->with('car')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return inertia('Client/Reservations/Index', [
            'reservations' => $reservations,
        ]);
    }

    public function show($id)
    {
        $reservation = Reservation::where('user_id', auth()->id())->findOrFail($id);
        $reservation->load(['user', 'car', 'payments.files']);

        return inertia('Client/Reservations/Show', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
        ]);
    }

    public function submitPaymentProof(Request $request, $id)
    {
        $reservation = Reservation::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'proof' => ['required', 'array', 'size:1'],
            'proof.*' => ['required', 'string'],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ((float) $reservation->required_advance_amount <= 0) {
            throw ValidationException::withMessages(['proof' => "Aucune avance n'est requise pour cette réservation."]);
        }
        if ($reservation->status !== ReservationStatus::PENDING) {
            throw ValidationException::withMessages(['proof' => "Une preuve d'avance ne peut être envoyée que pour une réservation en attente."]);
        }

        $existing = $reservation->payments()
            ->where('payment_type', 'advance')
            ->whereIn('status', [PaymentStatus::PENDING->value, PaymentStatus::COMPLETED->value])
            ->exists();
        if ($existing) {
            throw ValidationException::withMessages(['proof' => "Une preuve d'avance est déjà en attente ou approuvée."]);
        }

        $payment = Payment::create([
            'reservation_id' => $reservation->id,
            'user_id' => $request->user()->id,
            'amount' => $reservation->required_advance_amount,
            'currency' => config('app.currency_code'),
            'payment_method' => PaymentMethod::ALGERIA_POST,
            'payment_type' => 'advance',
            'status' => PaymentStatus::PENDING,
            'transaction_id' => $validated['transaction_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->filePondService->handleFileUploads($payment, $validated['proof'], 'proof');

        return back()->with('success', "Votre preuve de paiement a été envoyée et attend la vérification de l'agence.");
    }

    public function print($id)
    {
        $reservation = Reservation::where('user_id', auth()->id())->findOrFail($id);
        $reservation->load(['user', 'car', 'secondaryDriver', 'payments', 'fuelTankRecords.recordedBy']);

        $pdf = Pdf::loadView('admin.reservations.print', [
            'reservation' => $reservation,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
            'currency' => config('app.currency_symbol'),
            'agency' => CompanySetting::current(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($reservation->reservation_number . '.pdf');
    }
}
