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
            'faviconFiles' => $settings->files
                ->where('collection', 'favicon')
                ->map(fn ($file) => ['id' => $file->id, 'url' => $settings->favicon_url])
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
            'teacher_login_disabled' => ['required', 'boolean'],
            'logo_temp_folders' => ['array'],
            'logo_temp_folders.*' => ['string'],
            'logo_removed_files' => ['array'],
            'logo_removed_files.*' => ['integer'],
            'favicon_temp_folders' => ['array'],
            'favicon_temp_folders.*' => ['string'],
            'favicon_removed_files' => ['array'],
            'favicon_removed_files.*' => ['integer'],
        ]);

        $settings->update(collect($validated)->except([
            'logo_temp_folders', 'logo_removed_files',
            'favicon_temp_folders', 'favicon_removed_files',
        ])->toArray());

        $this->syncCollection($request, $settings, 'logo');
        $this->syncCollection($request, $settings, 'favicon');

        return back()->with('success', "Les paramètres de l'école ont été mis à jour avec succès.");
    }

    private function syncCollection(Request $request, CompanySetting $settings, string $collection): void
    {
        $tempFolders = $request->input("{$collection}_temp_folders", []);
        $removedIds = $request->input("{$collection}_removed_files", []);
        if ($tempFolders !== []) {
            $removedIds = array_values(array_unique(array_merge(
                $removedIds,
                $settings->files()->where('collection', $collection)->pluck('id')->all(),
            )));
        }

        $this->filePondService->handleFileUpdates($settings, $tempFolders, $removedIds, $collection);
    }
}
