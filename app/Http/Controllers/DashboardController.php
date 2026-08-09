<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\Expense;
use App\Models\Student;
use App\Models\User;
use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($request->user()->role !== UserRole::ADMIN) {
            return Inertia::render('Dashboard', ['dashboard' => null]);
        }

        $today = today();
        $activeForms = EnrollmentForm::query()
            ->with(['course:id,title,code,price', 'teacher:id,name', 'classroom:id,name,capacity'])
            ->withCount(['enrollments as confirmed_count' => fn ($query) => $query->whereNotNull('confirmed_at')])
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();
        $upcomingForms = EnrollmentForm::query()
            ->with(['course:id,title,code', 'teacher:id,name', 'classroom:id,name'])
            ->withCount(['enrollments as confirmed_count' => fn ($query) => $query->whereNotNull('confirmed_at')])
            ->where('is_active', true)
            ->whereDate('start_date', '>', $today)
            ->orderBy('start_date')
            ->limit(5)
            ->get();
        $nearCapacity = EnrollmentForm::query()
            ->with(['course:id,title,code'])
            ->withCount(['enrollments as confirmed_count' => fn ($query) => $query->whereNotNull('confirmed_at')])
            ->where('is_active', true)
            ->get()
            ->filter(fn ($form) => $form->max_students > 0 && ($form->confirmed_count / $form->max_students) >= .8)
            ->sortByDesc(fn ($form) => $form->confirmed_count / $form->max_students)
            ->take(5)
            ->values();
        $latestEnrollments = CourseEnrollment::query()
            ->with('form.course:id,title,code')
            ->whereNotNull('confirmed_at')
            ->latest('confirmed_at')
            ->limit(6)
            ->get();
        $todaySessions = TrainingSession::with(['group.plan.course:id,title', 'group:id,name,training_plan_id', 'classroom:id,name', 'teacher:id,name'])
            ->whereBetween('starts_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->orderBy('starts_at')->get();
        $upcomingSessions = TrainingSession::with(['group.plan.course:id,title', 'group:id,name,training_plan_id', 'classroom:id,name', 'teacher:id,name'])
            ->where('starts_at', '>', now())->orderBy('starts_at')->limit(6)->get();
        $occupiedRoomIds = $todaySessions->pluck('classroom_id')->unique();
        $activeRooms = Classroom::where('is_active', true)->count();

        $activities = collect()
            ->merge(Student::latest()->limit(3)->get()->map(fn ($student) => [
                'type' => 'student', 'title' => "Apprenant ajouté : {$student->full_name}", 'date' => $student->created_at,
            ]))
            ->merge(Expense::with('employee:id,name')->latest()->limit(3)->get()->map(fn ($expense) => [
                'type' => $expense->category === 'Salaire' ? 'salary' : 'expense',
                'title' => $expense->category === 'Salaire' ? 'Salaire enregistré : '.($expense->employee?->name ?? $expense->vendor) : "Dépense enregistrée : {$expense->title}",
                'date' => $expense->created_at,
            ]))
            ->merge(EnrollmentForm::with('course:id,title')->latest()->limit(3)->get()->map(fn ($form) => [
                'type' => 'formation', 'title' => "Inscriptions ouvertes : {$form->course->title}", 'date' => $form->created_at,
            ]))
            ->sortByDesc('date')->take(7)->values();

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'stats' => [
                    'students' => Student::where('is_active', true)->count(),
                    'monthly_enrollments' => CourseEnrollment::whereNotNull('confirmed_at')->whereBetween('confirmed_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->count(),
                    'ongoing_courses' => $activeForms->count(),
                    'active_groups' => $activeForms->sum('groups_count'),
                    'monthly_expenses' => (float) Expense::whereBetween('expense_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->sum('amount'),
                    'teachers_today' => $todaySessions->pluck('teacher_id')->unique()->count(),
                    'rooms_occupied' => $occupiedRoomIds->count(),
                    'rooms_available' => max(0, $activeRooms - $occupiedRoomIds->count()),
                ],
                'finance' => ['collected' => null, 'remaining' => null, 'overdue' => null, 'upcoming' => null],
                'attendance' => ['rate' => null, 'recent_absences' => [], 'missing_documents' => []],
                'schedule' => ['today' => $todaySessions, 'upcoming' => $upcomingSessions],
                'active_forms' => $activeForms,
                'upcoming_forms' => $upcomingForms,
                'near_capacity' => $nearCapacity,
                'latest_enrollments' => $latestEnrollments,
                'activities' => $activities,
                'alerts' => [],
                'currency' => ['symbol' => config('app.currency_symbol'), 'code' => config('app.currency_code')],
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
