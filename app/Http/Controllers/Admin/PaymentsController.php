<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ReservationMailer;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentsController extends Controller
{
    public function index(Request $request): Response
    {
        $statusCounts = Payment::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $payments = Payment::query()
            ->with(['user:id,name,email', 'reservation:id,reservation_number', 'files'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('payment_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('reservation', fn ($query) => $query
                            ->where('reservation_number', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $statuses = collect(PaymentStatus::cases())->mapWithKeys(function ($status) {
            return [
                $status->value => [
                    'label' => $status->label(),
                    'count' => $statusCounts[$status->value] ?? 0,
                    'color' => $status->color(),
                ]
            ];
        })->toArray();

        $reservations = Reservation::query()
            ->with('user:id,name,email')
            ->latest()
            ->limit(250)
            ->get(['id', 'user_id', 'reservation_number', 'total_amount']);

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'statuses' => $statuses,
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn ($method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ])->values(),
            'reservations' => $reservations,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /** Record a payment received by staff (cash, bank transfer, etc.). */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => ['required', 'integer', Rule::exists('reservations', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $reservation = Reservation::findOrFail($validated['reservation_id']);

        Payment::create([
            ...$validated,
            'user_id' => $reservation->user_id,
            'currency' => config('app.currency_code'),
            'status' => PaymentStatus::COMPLETED,
            'processed_at' => now(),
        ]);

        app(ReservationMailer::class)->send(
            $reservation,
            'Payment received: ' . $reservation->reservation_number,
            'Un paiement a été enregistré pour votre réservation. Votre contrat mis à jour est joint.',
        );

        return back()->with('success', 'Le paiement a été enregistré comme approuvé.');
    }

    /** Approve a payment that was submitted by a client. */
    public function approve(Payment $payment)
    {
        $this->ensurePending($payment);

        $payment->update([
            'status' => PaymentStatus::COMPLETED,
            'processed_at' => now(),
        ]);

        app(ReservationMailer::class)->send(
            $payment->reservation,
            'Paiement approuvé : ' . $payment->reservation->reservation_number,
            'Le paiement transmis a été approuvé. Votre contrat mis à jour est joint.',
        );

        return back()->with('success', 'Le paiement a été approuvé.');
    }

    /** Decline a payment that was submitted by a client. */
    public function disapprove(Request $request, Payment $payment)
    {
        $this->ensurePending($payment);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment->update([
            'status' => PaymentStatus::FAILED,
            'notes' => $validated['notes'] ?? $payment->notes,
            'processed_at' => now(),
        ]);

        app(ReservationMailer::class)->send(
            $payment->reservation,
            'Paiement refusé : ' . $payment->reservation->reservation_number,
            'Le paiement transmis a été refusé. Consultez le contrat joint et contactez-nous si vous avez besoin d’aide.',
        );

        return back()->with('success', 'Le paiement a été refusé.');
    }

    private function ensurePending(Payment $payment): void
    {
        abort_unless($payment->status === PaymentStatus::PENDING, 422, 'Only pending payments can be reviewed.');
    }
}
