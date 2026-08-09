<?php

namespace App\Http\Controllers;

use App\Enums\CarStatus;
use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\CompanySetting;
use App\Models\Driver;
use App\Models\Reservation;
use App\Services\ReservationMailer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function show(Car $car)
    {
        if (CompanySetting::current()->booking_disabled) {
            return redirect()->route('fleet')->with('error', 'Les réservations en ligne sont actuellement indisponibles.');
        }

        // Check if car is available for booking
        if (!in_array($car->status, [CarStatus::AVAILABLE, CarStatus::RESERVED, CarStatus::RENTED])) {
            return redirect()->route('fleet')->with('error', 'Ce véhicule n’est pas disponible à la réservation.');
        }

        $unavailablePeriods = $car->reservations()
            ->whereIn('status', [ReservationStatus::CONFIRMED->value, ReservationStatus::ACTIVE->value])
            ->orderBy('start_date')
            ->get(['start_date', 'end_date'])
            ->map(fn (Reservation $reservation) => [
                'start_date' => $reservation->start_date->toDateString(),
                'end_date' => $reservation->end_date->toDateString(),
            ]);

        $approvedDrivers = Auth::check()
            ? Auth::user()->drivers()->where('approval_status', 'approved')->get(['id', 'full_name', 'phone'])
            : collect();

        return inertia('Booking', compact('car', 'unavailablePeriods', 'approvedDrivers'));
    }

    public function book(Car $car, Request $request, ReservationMailer $reservationMailer)
    {
        if (CompanySetting::current()->booking_disabled) {
            return redirect()->route('fleet')->with('error', 'Les réservations en ligne sont actuellement indisponibles.');
        }

        // check car is available for booking
        if (!in_array($car->status, [CarStatus::AVAILABLE, CarStatus::RESERVED, CarStatus::RENTED])) {
            return redirect()->route('fleet')->with('error', 'Ce véhicule n’est pas disponible à la réservation.');
        }

        // check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Vous devez vous connecter pour réserver un véhicule.');
        }

        // form validation
        $request->validate([
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'pickup_location'  => 'required|string|max:255',
            'return_location'  => 'required|string|max:255',
            'secondary_driver_mode' => 'nullable|in:none,existing,new',
            'secondary_driver_id' => 'nullable|required_if:secondary_driver_mode,existing|integer',
            'new_driver_full_name' => 'nullable|required_if:secondary_driver_mode,new|string|max:255',
            'new_driver_phone' => 'nullable|required_if:secondary_driver_mode,new|string|max:30',
            'new_driver_email' => 'nullable|email|max:255',
            'new_driver_license' => 'nullable|required_if:secondary_driver_mode,new|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $secondaryDriver = null;
        if ($request->secondary_driver_mode === 'existing') {
            $secondaryDriver = Auth::user()->drivers()
                ->whereKey($request->secondary_driver_id)
                ->where('approval_status', 'approved')
                ->first();

            if (!$secondaryDriver) {
                throw ValidationException::withMessages([
                    'secondary_driver_id' => 'Sélectionnez un conducteur approuvé de votre liste.',
                ]);
            }
        }

        if ($request->secondary_driver_mode === 'new' && Auth::user()->drivers()->count() >= 3) {
            throw ValidationException::withMessages([
                'new_driver_full_name' => 'Vous avez atteint la limite de 3 conducteurs.',
            ]);
        }

        // convert dates to Carbon
        $startDate = Carbon::parse($request->start_date);
        $endDate   = Carbon::parse($request->end_date);

        $userHasOverlappingReservation = Reservation::query()
            ->where('car_id', $car->id)
            ->where('user_id', Auth::id())
            ->whereIn('status', [
                ReservationStatus::PENDING->value,
                ReservationStatus::CONFIRMED->value,
                ReservationStatus::ACTIVE->value,
            ])
            ->betweenDates($startDate->toDateString(), $endDate->toDateString())
            ->exists();

        if ($userHasOverlappingReservation) {
            throw ValidationException::withMessages([
                'start_date' => 'Vous avez déjà une réservation pour ce véhicule pendant les dates sélectionnées.',
            ]);
        }

        if (!$car->isAvailable($startDate->toDateString(), $endDate->toDateString())) {
            throw ValidationException::withMessages([
                'start_date' => 'Ce véhicule est déjà réservé pour tout ou partie des dates sélectionnées.',
                'end_date' => 'Veuillez choisir des dates en dehors de la période indisponible.',
            ]);
        }

        // calculate days (always at least 1)
        $days = max(1, $startDate->diffInDays($endDate));

        // ensure daily rate is positive
        $dailyRate = abs($car->price_per_day);

        // calculations
        $subtotal   = $dailyRate * $days;
        $agencySettings = CompanySetting::current();
        $taxRate    = $agencySettings->tax_enabled ? (float) $agencySettings->tax_rate / 100 : 0;
        $taxAmount  = $subtotal * $taxRate;
        $discount   = 0;
        $total      = $subtotal + $taxAmount - $discount;
        $advancePercentage = (float) $agencySettings->online_advance_percentage;
        $requiredAdvance = round($total * ($advancePercentage / 100), 2);

        $reservation = DB::transaction(function () use ($request, $car, $startDate, $endDate, $days, $dailyRate, $subtotal, $taxAmount, $discount, $total, $advancePercentage, $requiredAdvance, $secondaryDriver) {
            $requestedDriver = null;
            if ($request->secondary_driver_mode === 'new') {
                $requestedDriver = Driver::create([
                    'user_id' => Auth::id(),
                    'full_name' => $request->new_driver_full_name,
                    'phone' => $request->new_driver_phone,
                    'email' => $request->new_driver_email,
                    'driving_license_path' => $request->file('new_driver_license')->store('driving-licenses/drivers', 'public'),
                    'approval_status' => 'pending',
                ]);
            }

            return Reservation::create([
            'car_id'          => $car->id,
            'user_id'         => Auth::id(),
            'secondary_driver_id' => $secondaryDriver?->id,
            'requested_driver_id' => $requestedDriver?->id,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'pickup_location' => $request->pickup_location,
            'return_location' => $request->return_location,
            'total_days'      => $days,
            'daily_rate'      => $dailyRate,
            'subtotal'        => $subtotal,
            'tax_amount'      => $taxAmount,
            'discount'        => $discount,
            'total_amount'    => $total,
            'security_deposit_amount' => $car->security_deposit,
            'advance_percentage' => $advancePercentage,
            'required_advance_amount' => $requiredAdvance,
            'status'          => ReservationStatus::PENDING,
            ]);
        });

        $reservationMailer->send(
            $reservation,
            'Réservation reçue : ' . $reservation->reservation_number,
            'Votre réservation a été créée et attend la validation de notre équipe. Nous vous informerons par e-mail dès qu’elle sera approuvée ou refusée.',
        );

        // $car->update([
        //     'status' => CarStatus::RESERVED,
        // ]);

        return redirect()->route('booking.confirmation', $reservation);
    }


    public function confirmation(Reservation $reservation)
    {
        // Make sure user can only see their own reservations
        if ($reservation->user_id !== Auth::user()->id) {
            return redirect()->route('fleet');
        }

        return inertia('BookingConfirmation', [
            'reservation' => $reservation->load(['car', 'user']),
        ]);
    }
}
