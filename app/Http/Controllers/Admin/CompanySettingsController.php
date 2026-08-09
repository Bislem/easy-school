<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use MohamedGaldi\ViltFilepond\Services\FilePondService;

class CompanySettingsController extends Controller
{
    public function __construct(private FilePondService $filePondService) {}

    public function edit(): Response
    {
        $settings = CompanySetting::firstOrCreate([], CompanySetting::defaults());
        $settings->load('files');

        return Inertia::render('Admin/Settings/Edit', [
            'settings' => $settings,
            'logoFiles' => $settings->files
                ->where('collection', 'logo')
                ->map(fn ($file) => ['id' => $file->id, 'url' => $settings->logo_url])
                ->values(),
        ]);
    }

    public function update(Request $request)
    {
        $settings = CompanySetting::firstOrCreate([], CompanySetting::defaults());

        $validated = $request->validate([
            'trading_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'website_disabled' => ['required', 'boolean'],
            'booking_disabled' => ['required', 'boolean'],
            'client_login_disabled' => ['required', 'boolean'],
            'tax_enabled' => ['required', 'boolean'],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
            'online_advance_percentage' => ['required', 'numeric', 'between:0,100'],
            'logo_temp_folders' => ['array'],
            'logo_temp_folders.*' => ['string'],
            'logo_removed_files' => ['array'],
            'logo_removed_files.*' => ['integer'],
        ]);

        $settings->update(collect($validated)->except(['logo_temp_folders', 'logo_removed_files'])->toArray());

        $tempFolders = $request->input('logo_temp_folders', []);
        $removedIds = $request->input('logo_removed_files', []);
        if ($tempFolders !== []) {
            $removedIds = array_values(array_unique(array_merge(
                $removedIds,
                $settings->files()->where('collection', 'logo')->pluck('id')->all(),
            )));
        }

        $this->filePondService->handleFileUpdates($settings, $tempFolders, $removedIds, 'logo');

        return back()->with('success', "Les paramètres de l'agence ont été mis à jour avec succès.");
    }
}
