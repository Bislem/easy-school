<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AcademicYearCalendarEvent;
use App\Services\AcademicYearManager;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->allow($request, 'academic_year.view');

        return Inertia::render('Admin/AcademicYears/Index', ['years' => AcademicYear::with(['periods', 'calendarEvents'])->orderByDesc('start_date')->get()]);
    }

    public function store(Request $request, AcademicYearManager $manager): RedirectResponse
    {
        $this->allow($request, 'academic_year.create');
        $year = $manager->create($this->validated($request));
        $request->session()->put('academic_year_id', $year->id);

        return back()->with('success', 'Année scolaire créée avec ses trois trimestres.');
    }

    public function update(Request $request, AcademicYear $academicYear, AcademicYearManager $manager): RedirectResponse
    {
        $this->allow($request, 'academic_year.update');
        $manager->update($academicYear, $this->validated($request, $academicYear));

        return back()->with('success', 'Année scolaire mise à jour.');
    }

    public function activate(Request $request, AcademicYear $academicYear, AcademicYearManager $manager): RedirectResponse
    {
        $this->allow($request, 'academic_year.activate');
        $manager->activate($academicYear);
        session()->put('academic_year_id', $academicYear->id);

        return back()->with('success', 'Année scolaire activée.');
    }

    public function close(Request $request, AcademicYear $academicYear, AcademicYearManager $manager): RedirectResponse
    {
        $this->allow($request, 'academic_year.close');
        $manager->close($academicYear);

        return back()->with('success', 'Année scolaire clôturée. Les données restent consultables.');
    }

    public function archive(Request $request, AcademicYear $academicYear, AcademicYearManager $manager): RedirectResponse
    {
        $this->allow($request, 'academic_year.archive');
        $manager->archive($academicYear);

        return back()->with('success', 'Année scolaire archivée.');
    }

    public function select(Request $request): RedirectResponse
    {
        $this->allow($request, 'academic_year.view');
        $data = $request->validate(['academic_year_id' => ['required', TenantRule::exists('academic_years')]]);
        $request->session()->put('academic_year_id', $data['academic_year_id']);

        return back();
    }

    public function storeCalendarEvent(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->allow($request, 'academic_year.update');
        abort_unless($academicYear->isWritable(), 422, 'Cette année scolaire ne peut plus être modifiée.');
        $academicYear->calendarEvents()->create($this->validatedCalendarEvent($request, $academicYear));

        return back()->with('success', 'Période de vacances ou fermeture ajoutée.');
    }

    public function updateCalendarEvent(Request $request, AcademicYear $academicYear, AcademicYearCalendarEvent $calendarEvent): RedirectResponse
    {
        $this->allow($request, 'academic_year.update');
        abort_unless($calendarEvent->academic_year_id === $academicYear->id, 404);
        abort_unless($academicYear->isWritable(), 422, 'Cette année scolaire ne peut plus être modifiée.');
        $calendarEvent->update($this->validatedCalendarEvent($request, $academicYear));

        return back()->with('success', 'Période mise à jour.');
    }

    public function destroyCalendarEvent(Request $request, AcademicYear $academicYear, AcademicYearCalendarEvent $calendarEvent): RedirectResponse
    {
        $this->allow($request, 'academic_year.update');
        abort_unless($calendarEvent->academic_year_id === $academicYear->id, 404);
        abort_unless($academicYear->isWritable(), 422, 'Cette année scolaire ne peut plus être modifiée.');
        $calendarEvent->delete();

        return back()->with('success', 'Période supprimée.');
    }

    private function validated(Request $request, ?AcademicYear $year = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:30', TenantRule::unique('academic_years', 'name')->ignore($year)], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after:start_date'], 'notes' => ['nullable', 'string', 'max:3000'], 'periods' => [Rule::excludeIf($year === null && $request->input('periods') === []), 'array', 'size:3'], 'periods.*.name' => ['required_with:periods', 'string', 'max:80'], 'periods.*.start_date' => ['required_with:periods', 'date'], 'periods.*.end_date' => ['required_with:periods', 'date', 'after:periods.*.start_date']]);
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }

    private function validatedCalendarEvent(Request $request, AcademicYear $academicYear): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['winter_break', 'spring_break', 'closure'])],
            'starts_on' => ['required', 'date', 'after_or_equal:'.$academicYear->start_date->toDateString()],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on', 'before_or_equal:'.$academicYear->end_date->toDateString()],
            'applies_to' => ['required', Rule::in(['both', 'teachers', 'students'])],
            'is_paid_for_teachers' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
