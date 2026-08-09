<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentsController extends Controller
{
    public function index(Request $request): Response
    {
        $students = Student::query()
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Students/Index', [
            'students' => $students,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Student::create($this->validateStudent($request));

        return back()->with('success', 'Étudiant ajouté avec succès.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $student->update($this->validateStudent($request, $student));

        return back()->with('success', 'Étudiant mis à jour avec succès.');
    }

    public function toggleActive(Student $student): RedirectResponse
    {
        $student->update(['is_active' => ! $student->is_active]);

        return back()->with('success', $student->is_active
            ? "L'étudiant a été activé."
            : "L'étudiant a été désactivé.");
    }

    private function validateStudent(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('students')->ignore($student)],
            'phone' => ['required', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
