<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CoursesController extends Controller
{
    public function index(Request $request): Response
    {
        $courses = Course::query()
            ->when($request->string('search')->trim()->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Course::create($this->validateCourse($request));

        return back()->with('success', 'Formation créée avec succès.');
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $course->update($this->validateCourse($request, $course));

        return back()->with('success', 'Formation mise à jour avec succès.');
    }

    public function toggleActive(Course $course): RedirectResponse
    {
        $course->update(['is_active' => ! $course->is_active]);

        return back()->with('success', $course->is_active
            ? 'La formation a été activée.'
            : 'La formation a été désactivée.');
    }

    private function validateCourse(Request $request, ?Course $course = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('courses')->ignore($course)],
            'category' => ['nullable', 'string', 'max:100'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:100000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'description' => ['nullable', 'string', 'max:5000'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'prerequisites' => ['nullable', 'string', 'max:5000'],
            'is_certified' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
