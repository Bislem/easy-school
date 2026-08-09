<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\Car;
use App\Models\User;
use App\Models\Payment;
use App\Models\FuelTankRecord;
use App\Models\CompanySetting;
use App\Enums\PaymentMethod;
use App\Enums\CarStatus;
use App\Enums\ReservationStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Services\ReservationMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ReservationsController extends Controller
{
    /**
     * Display a listing of reservations.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');

        // Status counts for filter chips
        $statusCounts = Reservation::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $reservations = Reservation::query()
            ->with([
                'user:id,name,email',
                'car:id,make,model,year,license_plate',
            ])
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reservation_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('car', function ($cq) use ($search) {
                            $cq->where('make', 'like', "%{$search}%")
                                ->orWhere('model', 'like', "%{$search}%")
                                ->orWhere('license_plate', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status && $status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $statuses = collect(ReservationStatus::cases())->mapWithKeys(function ($st) use ($statusCounts) {
            $meta = ReservationStatus::getMeta();
            $statusMeta = collect($meta)->firstWhere('value', $st->value);
            
            return [
                $st->value => [
                    'label' => $statusMeta['label'] ?? ucfirst(str_replace('_', ' ', $st->value)),
                    'count' => $statusCounts[$st->value] ?? 0,
                    'color' => $statusMeta['color'] ?? '#6B7280',
                ],
            ];
        })->toArray();

        return Inertia::render('Admin/Reservations/Index', [
            'reservations' => $reservations,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $status,
            ],
            'statuses' => $statuses,
        ]);
    }

    /** Show the manual reservation form. */
    public function create(): Response
    {
        return Inertia::render('Admin/Reservations/Create', [
            'clients' => User::query()
                ->where('role', UserRole::CLIENT)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'is_active']),
            'cars' => Car::query()
                ->whereIn('status', [CarStatus::AVAILABLE, CarStatus::RESERVED, CarStatus::RENTED])
                ->orderBy('make')
                ->orderBy('model')
                ->get(['id', 'make', 'model', 'year', 'license_plate', 'price_per_day', 'security_deposit', 'status']),
            'statuses' => collect(ReservationStatus::getMeta())
                ->whereIn('value', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])
                ->values(),
            'currency' => [
                'symbol' => config('app.currency_symbol'),
                'code' => config('app.currency_code'),
            ],
            'tax' => [
                'enabled' => CompanySetting::current()->tax_enabled,
                'rate' => (float) CompanySetting::current()->tax_rate,
            ],
        ]);
    }

    /** Store a reservation entered by an administrator. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_mode' => ['required', Rule::in(['existing', 'new'])],
            'user_id' => ['nullable', 'required_if:client_mode,existing', Rule::exists('users', 'id')->where('role', UserRole::CLIENT->value)],
            'client_name' => ['nullable', 'required_if:client_mode,new', 'string', 'max:255'],
            'client_email' => ['nullable', 'required_if:client_mode,new', 'email', 'max:255', 'unique:users,email'],
            'client_phone' => ['nullable', 'required_if:client_mode,new', 'string', 'max:50'],
            'client_password' => ['nullable', 'required_if:client_mode,new', 'string', 'min:8', 'confirmed'],
            'car_id' => ['required', 'exists:cars,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'pickup_location' => ['required', 'string', 'max:255'],
            'return_location' => ['required', 'string', 'max:255'],
            'daily_rate' => ['required', 'numeric', 'min:0.01'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in([ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $car = Car::findOrFail($validated['car_id']);
        if (!in_array($car->status, [CarStatus::AVAILABLE, CarStatus::RESERVED, CarStatus::RENTED])) {
            throw ValidationException::withMessages(['car_id' => "Ce véhicule n'est pas disponible pour les réservations."]);
        }

        if ($validated['status'] === ReservationStatus::CONFIRMED->value
            && !$car->isAvailable($validated['start_date'], $validated['end_date'])) {
            throw ValidationException::withMessages([
                'start_date' => 'Ce véhicule est déjà réservé pour tout ou partie des dates sélectionnées.',
                'end_date' => 'Veuillez choisir une autre période.',
            ]);
        }

        $agencySettings = CompanySetting::current();
        $taxRate = $agencySettings->tax_enabled ? (float) $agencySettings->tax_rate / 100 : 0;

        $reservation = DB::transaction(function () use ($validated, $car, $taxRate) {
            $user = $validated['client_mode'] === 'new'
                ? tap(User::create([
                    'name' => $validated['client_name'],
                    'email' => $validated['client_email'],
                    'phone' => $validated['client_phone'],
                    'password' => $validated['client_password'],
                    'role' => UserRole::CLIENT,
                    'is_active' => true,
                ]), fn (User $user) => $user->forceFill(['email_verified_at' => now()])->save())
                : User::where('role', UserRole::CLIENT)->findOrFail($validated['user_id']);

            $start = Carbon::parse($validated['start_date']);
            $end = Carbon::parse($validated['end_date']);
            $totalDays = $start->diffInDays($end) + 1;
            $subtotal = round((float) $validated['daily_rate'] * $totalDays, 2);
            $taxAmount = round($subtotal * $taxRate, 2);
            $discount = (float) ($validated['discount_amount'] ?? 0);

            if ($discount > $subtotal + $taxAmount) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'La remise ne peut pas dépasser le sous-total avec taxes.',
                ]);
            }

            return Reservation::create([
                'user_id' => $user->id,
                'car_id' => $car->id,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'pickup_time' => $validated['pickup_time'] ?? null,
                'return_time' => $validated['return_time'] ?? null,
                'pickup_location' => $validated['pickup_location'],
                'return_location' => $validated['return_location'],
                'total_days' => $totalDays,
                'daily_rate' => $validated['daily_rate'],
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discount,
                'total_amount' => $subtotal + $taxAmount - $discount,
                'security_deposit_amount' => $car->security_deposit,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        $this->syncCarStatus($reservation);

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', 'La réservation manuelle a été créée avec succès.');
    }

    /**
     * Display the specified reservation details.
     */
    public function show(Reservation $reservation): Response
    {
        $reservation->load(['user', 'car', 'payments.files', 'fuelTankRecords.recordedBy']);
        $paidAmount = $this->paidAmount($reservation);

        return Inertia::render('Admin/Reservations/Show', [
            'reservation' => $reservation,
            'paidAmount' => $paidAmount,
            'isPaid' => $paidAmount >= (float) $reservation->total_amount,
            'statusMeta' => ReservationStatus::getMeta(),
            'paymentStatusMeta' => PaymentStatus::getMeta(),
        ]);
    }

    /**
     * Show the form for editing the specified reservation.
     */
    public function edit(Reservation $reservation): Response
    {
        $reservation->load(['user:id,name,email', 'car:id,make,model,year,license_plate']);

        return Inertia::render('Admin/Reservations/Edit', [
            'reservation' => $reservation,
            'enums' => [
                'statuses' => ReservationStatus::getMeta(),
            ],
        ]);
    }

    /**
     * Update the specified reservation in storage.
     */
    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'return_time' => ['nullable', 'date_format:H:i'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'return_location' => ['nullable', 'string', 'max:255'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::enum(ReservationStatus::class)],
            'cancellation_reason' => ['nullable', 'string'],
        ]);

        if ($validated['status'] === ReservationStatus::CANCELLED->value) {
            abort_if(
                $reservation->status === ReservationStatus::CONFIRMED || $this->isFullyPaid($reservation),
                422,
                'Les réservations confirmées ou entièrement payées ne peuvent pas être annulées.',
            );
        }

        if (in_array($validated['status'], [ReservationStatus::CONFIRMED->value, ReservationStatus::ACTIVE->value])) {
            $this->ensureCarAvailable($reservation, $validated['start_date'], $validated['end_date']);
        }

        // Restrict this action
        // return redirect()
        //     ->back()
        //     ->with('restricted_action', 'This is a demo version. For security reasons, create, update, and delete actions are disabled.');


        $reservation->fill($validated);
        // Recalculate totals when dates or discount change
        $agencySettings = CompanySetting::current();
        $configuredTaxRate = $agencySettings->tax_enabled ? (float) $agencySettings->tax_rate / 100 : 0;
        $taxRate = (float) $reservation->getOriginal('subtotal') > 0
            ? (float) $reservation->getOriginal('tax_amount') / (float) $reservation->getOriginal('subtotal')
            : $configuredTaxRate;
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $totalDays = $start->diffInDays($end) + 1;
        $reservation->total_days = $totalDays;
        $reservation->subtotal = $reservation->daily_rate * $totalDays;
        $reservation->tax_amount = round($reservation->subtotal * $taxRate, 2);
        $reservation->total_amount = $reservation->subtotal + $reservation->tax_amount - (float)($reservation->discount_amount ?? 0);

        // Maintain cancellation metadata
        if ($reservation->status === ReservationStatus::CANCELLED && !$reservation->cancelled_at) {
            $reservation->cancelled_at = now();
        }
        if ($reservation->status !== ReservationStatus::CANCELLED) {
            $reservation->cancellation_reason = null;
            $reservation->cancelled_at = null;
        }

        $reservation->save();
        $this->syncCarStatus($reservation);

        $this->notifyClient($reservation, 'Réservation mise à jour', 'Les détails de votre réservation ont été mis à jour par notre équipe.');

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', 'La réservation a été mise à jour avec succès.');
    }

    /** Approve a newly submitted reservation. */
    public function approve(Reservation $reservation)
    {
        abort_unless($reservation->status === ReservationStatus::PENDING, 422, 'Seules les réservations en attente peuvent être approuvées.');
        $approvedPayments = (float) $reservation->payments()->where('status', PaymentStatus::COMPLETED)->sum('amount');
        abort_if($approvedPayments < (float) $reservation->required_advance_amount, 422, "L'avance requise doit être approuvée avant de confirmer cette réservation.");
        $this->ensureCarAvailable($reservation, $reservation->start_date->toDateString(), $reservation->end_date->toDateString());

        $reservation->update(['status' => ReservationStatus::CONFIRMED]);
        $this->syncCarStatus($reservation);
        $this->notifyClient($reservation, 'Réservation approuvée', 'Votre réservation a été approuvée. Nous avons hâte de vous accueillir.');

        return back()->with('success', 'Réservation approuvée.');
    }

    /** Reject a newly submitted reservation and retain the reason. */
    public function reject(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->status === ReservationStatus::PENDING, 422, 'Seules les réservations en attente peuvent être rejetées.');
        abort_if($this->isFullyPaid($reservation), 422, 'Les réservations entièrement payées ne peuvent pas être rejetées.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->cancelReservation($reservation, $validated['reason'] ?? 'Réservation rejetée par un administrateur.');
        $this->syncCarStatus($reservation);
        $this->notifyClient($reservation, 'Réservation refusée', 'Votre demande de réservation a été refusée. Veuillez consulter le contrat joint pour les détails enregistrés.');

        return back()->with('success', 'Réservation rejetée.');
    }

    /** Cancel a reservation that has not started yet. */
    public function cancel(Request $request, Reservation $reservation)
    {
        abort_if(in_array($reservation->status, [ReservationStatus::CONFIRMED, ReservationStatus::CANCELLED, ReservationStatus::COMPLETED]), 422, 'Cette réservation ne peut pas être annulée.');
        abort_if($this->isFullyPaid($reservation), 422, 'Les réservations entièrement payées ne peuvent pas être annulées.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->cancelReservation($reservation, $validated['reason'] ?? null);
        $this->syncCarStatus($reservation);
        $this->notifyClient($reservation, 'Réservation annulée', 'Votre réservation a été annulée. Veuillez consulter le contrat joint pour les détails enregistrés.');

        return back()->with('success', 'Réservation annulée.');
    }

    /** Record the outstanding reservation balance as a completed cash payment. */
    public function markPaid(Reservation $reservation)
    {
        abort_if($reservation->status === ReservationStatus::CANCELLED, 422, 'Les réservations annulées ne peuvent pas être marquées comme payées.');

        DB::transaction(function () use ($reservation) {
            $reservation = Reservation::lockForUpdate()->findOrFail($reservation->id);
            $paidAmount = Payment::query()
                ->where('reservation_id', $reservation->id)
                ->where('status', PaymentStatus::COMPLETED)
                ->sum('amount');
            $outstandingAmount = round((float) $reservation->total_amount - (float) $paidAmount, 2);

            abort_if($outstandingAmount <= 0, 422, 'Cette réservation est déjà entièrement payée.');

            Payment::create([
                'reservation_id' => $reservation->id,
                'user_id' => $reservation->user_id,
                'amount' => $outstandingAmount,
                'currency' => config('app.currency_code'),
                'payment_method' => PaymentMethod::CASH,
                'status' => PaymentStatus::COMPLETED,
                'notes' => "Enregistré manuellement depuis l'écran d'administration des réservations.",
                'processed_at' => now(),
            ]);
        });

        $this->notifyClient($reservation, 'Paiement reçu', 'Un paiement en espèces a été enregistré pour votre réservation. Votre contrat mis à jour est joint.');

        return back()->with('success', 'Le solde restant a été enregistré comme paiement en espèces.');
    }

    /** Start a confirmed reservation when the vehicle is handed over. */
    public function start(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->status === ReservationStatus::CONFIRMED, 422, 'Seules les réservations confirmées peuvent être démarrées.');
        $this->ensureCarAvailable($reservation, $reservation->start_date->toDateString(), $reservation->end_date->toDateString());

        $fuelRecord = $this->validateFuelRecord($request);

        DB::transaction(function () use ($reservation, $fuelRecord) {
            $reservation->update(['status' => ReservationStatus::ACTIVE]);
            $this->recordFuelLevel($reservation, FuelTankRecord::AT_RENTAL_START, $fuelRecord);
        });
        $this->syncCarStatus($reservation);
        $this->notifyClient($reservation, 'Location démarrée', 'Votre réservation est maintenant active. Veuillez conserver le contrat joint dans vos dossiers.');

        return back()->with('success', 'Réservation marquée comme en cours.');
    }

    /** Complete an active reservation after the vehicle is returned. */
    public function complete(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->status === ReservationStatus::ACTIVE, 422, 'Seules les réservations en cours peuvent être terminées.');

        $fuelRecord = $this->validateFuelRecord($request);

        DB::transaction(function () use ($reservation, $fuelRecord) {
            $reservation->update(['status' => ReservationStatus::COMPLETED]);
            $this->recordFuelLevel($reservation, FuelTankRecord::AT_RENTAL_END, $fuelRecord);
        });
        $this->syncCarStatus($reservation);
        $this->notifyClient($reservation, 'Location terminée', 'Votre réservation a été marquée comme terminée. Votre contrat final est joint.');

        return back()->with('success', 'Réservation marquée comme terminée.');
    }

    /** Record that a confirmed client did not arrive for pickup. */
    public function noShow(Reservation $reservation)
    {
        abort_unless($reservation->status === ReservationStatus::CONFIRMED, 422, 'Seules les réservations confirmées peuvent être marquées comme client absent.');

        $reservation->update(['status' => ReservationStatus::NO_SHOW]);
        $this->syncCarStatus($reservation);
        $this->notifyClient($reservation, 'Réservation marquée comme client absent', 'Votre réservation a été marquée comme client absent. Veuillez consulter le contrat joint pour les détails enregistrés.');

        return back()->with('success', 'Réservation marquée comme client absent.');
    }

    private function cancelReservation(Reservation $reservation, ?string $reason): void
    {
        $reservation->update([
            'status' => ReservationStatus::CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);
    }

    /** Validate a reading supplied immediately before handing over or receiving a car. */
    private function validateFuelRecord(Request $request): array
    {
        return $request->validate([
            'fuel_level' => ['required', 'integer', 'between:0,100'],
            'fuel_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function recordFuelLevel(Reservation $reservation, string $recordType, array $fuelRecord): void
    {
        FuelTankRecord::create([
            'car_id' => $reservation->car_id,
            'reservation_id' => $reservation->id,
            'recorded_by' => auth()->id(),
            'record_type' => $recordType,
            'fuel_level' => $fuelRecord['fuel_level'],
            'notes' => $fuelRecord['fuel_notes'] ?? null,
            'recorded_at' => now(),
        ]);
    }

    private function notifyClient(Reservation $reservation, string $subject, string $message): void
    {
        app(ReservationMailer::class)->send(
            $reservation,
            $subject . ': ' . $reservation->reservation_number,
            $message,
        );
    }

    private function paidAmount(Reservation $reservation): float
    {
        return (float) Payment::query()
            ->where('reservation_id', $reservation->id)
            ->where('status', PaymentStatus::COMPLETED)
            ->sum('amount');
    }

    private function isFullyPaid(Reservation $reservation): bool
    {
        return $this->paidAmount($reservation) >= (float) $reservation->total_amount;
    }

    private function ensureCarAvailable(Reservation $reservation, string $startDate, string $endDate): void
    {
        $car = $reservation->car;

        abort_if(!$car || !in_array($car->status, [CarStatus::AVAILABLE, CarStatus::RESERVED, CarStatus::RENTED]), 422, "Ce véhicule n'est pas disponible pour les réservations.");
        abort_unless($car->isAvailable($startDate, $endDate, $reservation->id), 422, 'Ce véhicule est déjà réservé pour tout ou partie des dates sélectionnées.');
    }

    private function syncCarStatus(Reservation $reservation): void
    {
        $car = $reservation->car()->first();

        if (!$car || !in_array($car->status, [CarStatus::AVAILABLE, CarStatus::RESERVED, CarStatus::RENTED])) {
            return;
        }

        if ($car->reservations()->where('status', ReservationStatus::ACTIVE->value)->exists()) {
            $car->update(['status' => CarStatus::RENTED]);
        } elseif ($car->reservations()->where('status', ReservationStatus::CONFIRMED->value)->exists()) {
            $car->update(['status' => CarStatus::RESERVED]);
        } else {
            $car->update(['status' => CarStatus::AVAILABLE]);
        }
    }

    /**
     * Generate and download a PDF for the reservation details.
     */
    public function print(Reservation $reservation)
    {
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
