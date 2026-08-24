<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\SchoolSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomsController extends Controller
{
    public function index(Request $request): Response
    {
        $classrooms = Classroom::query()->with('site:id,name,code,wilaya,commune')
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('site', fn ($site) => $site->where('name', 'like', "%{$search}%")->orWhere('wilaya', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->when($request->filled('site_id'), fn ($query) => $query->where('school_site_id', $request->integer('site_id')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Classrooms/Index', [
            'classrooms' => $classrooms,
            'sites' => SchoolSite::orderBy('name')->get(['id', 'name', 'code', 'wilaya', 'is_active']),
            'filters' => $request->only(['search', 'status', 'site_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Classroom::create($this->validateClassroom($request));

        return back()->with('success', 'Salle créée avec succès.');
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $classroom->update($this->validateClassroom($request, $classroom));

        return back()->with('success', 'Salle mise à jour avec succès.');
    }

    public function toggleActive(Classroom $classroom): RedirectResponse
    {
        $classroom->update(['is_active' => ! $classroom->is_active]);

        return back()->with('success', $classroom->is_active
            ? 'La salle a été activée.'
            : 'La salle a été désactivée.');
    }

    private function validateClassroom(Request $request, ?Classroom $classroom = null): array
    {
        return $request->validate([
            'school_site_id' => ['required', 'exists:school_sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('classrooms')->ignore($classroom)],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
