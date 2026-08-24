<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchoolSitesController extends Controller
{
    public function index(Request $request): Response
    {
        $sites = SchoolSite::query()->withCount('classrooms')
            ->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
                ->orWhere('wilaya', 'like', "%{$search}%")->orWhere('commune', 'like', "%{$search}%")))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Admin/SchoolSites/Index', [
            'sites' => $sites,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SchoolSite::create($this->validated($request));
        return back()->with('success', 'Site créé avec succès.');
    }

    public function update(Request $request, SchoolSite $site): RedirectResponse
    {
        $site->update($this->validated($request, $site));
        return back()->with('success', 'Site mis à jour avec succès.');
    }

    public function toggleActive(SchoolSite $site): RedirectResponse
    {
        $site->update(['is_active' => ! $site->is_active]);
        return back()->with('success', $site->is_active ? 'Le site a été activé.' : 'Le site a été désactivé.');
    }

    private function validated(Request $request, ?SchoolSite $site = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('school_sites')->ignore($site)],
            'wilaya' => ['required', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
