<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Services\TenantStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function __construct(private TenantStorageService $tenantStorage) {}

    public function index(Request $request): Response
    {
        return Inertia::render('settings/Drivers', [
            'drivers' => $request->user()->drivers()->latest()->get(),
            'maximumDrivers' => 3,
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->drivers()->count() >= 3) {
            throw ValidationException::withMessages(['full_name' => 'Vous pouvez enregistrer au maximum 3 conducteurs.']);
        }

        $validated = $this->validateDriver($request, true);
        unset($validated['driving_license']);
        $driver = $request->user()->drivers()->create($validated + ['approval_status' => 'pending']);
        $driver->update(['driving_license_path' => $this->tenantStorage->store($request->file('driving_license'), 'driving-licenses/drivers', 'public', TenantStorageService::ATTACHMENTS, 'drivers', $driver)]);

        return back()->with('success', 'Le conducteur a été ajouté et attend la validation d’un administrateur.');
    }

    public function update(Request $request, Driver $driver)
    {
        $this->authorizeOwner($request, $driver);
        $validated = $this->validateDriver($request, false);
        $oldPath = $driver->driving_license_path;

        if ($request->hasFile('driving_license')) {
            $validated['driving_license_path'] = $this->tenantStorage->store($request->file('driving_license'), 'driving-licenses/drivers', 'public', TenantStorageService::ATTACHMENTS, 'drivers', $driver);
        }

        unset($validated['driving_license']);
        $driver->update($validated + [
            'approval_status' => 'pending', 'rejection_reason' => null,
            'approved_at' => null, 'approved_by' => null,
        ]);

        $driver->reservations()
            ->whereIn('status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])
            ->update([
                'secondary_driver_id' => null,
                'requested_driver_id' => $driver->id,
            ]);

        if (isset($validated['driving_license_path']) && $oldPath) {
            $this->tenantStorage->delete($oldPath);
        }

        return back()->with('success', 'Le conducteur a été modifié et doit être validé à nouveau.');
    }

    public function destroy(Request $request, Driver $driver)
    {
        $this->authorizeOwner($request, $driver);
        if ($driver->reservations()->exists() || $driver->requestedReservations()->exists()) {
            return back()->with('error', 'Ce conducteur est lié à une réservation et ne peut pas être supprimé.');
        }

        $path = $driver->driving_license_path;
        $driver->delete();
        $this->tenantStorage->delete($path);

        return back()->with('success', 'Le conducteur a été supprimé.');
    }

    private function validateDriver(Request $request, bool $licenseRequired): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'driving_license' => [$licenseRequired ? 'required' : 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);
    }

    private function authorizeOwner(Request $request, Driver $driver): void
    {
        abort_unless($driver->user_id === $request->user()->id, 404);
    }
}
