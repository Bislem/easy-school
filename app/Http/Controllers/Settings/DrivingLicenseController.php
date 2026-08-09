<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DrivingLicenseUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DrivingLicenseController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/DrivingLicense', [
            'license' => [
                'birth_date' => $request->user()->birth_date,
                'number' => $request->user()->driving_license_number,
                'delivered_at' => $request->user()->driving_license_delivered_at,
                'authority' => $request->user()->driving_license_authority,
                'document_url' => $request->user()->driving_license_url,
            ],
        ]);
    }

    public function update(DrivingLicenseUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->except('driving_license_copy'));
        $licenseChanged = $user->isDirty([
            'birth_date', 'driving_license_number',
            'driving_license_delivered_at', 'driving_license_authority',
        ]) || $request->hasFile('driving_license_copy');

        if (!$licenseChanged) {
            return to_route('driving-license.edit')->with('status', 'Aucune modification détectée.');
        }

        if ($request->hasFile('driving_license_copy')) {
            $oldPath = $user->driving_license_path;
            $user->driving_license_path = $request->file('driving_license_copy')->store('driving-licenses', 'public');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $user->forceFill([
            'approval_status' => 'pending',
            'is_active' => false,
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => null,
        ])->save();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', "Vos informations de permis ont été mises à jour et attendent l'approbation de l'agence.");
    }
}
