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
use App\Models\StudentInstallment;
use App\Models\SessionAttendance;
use App\Models\SalaryStatement;
use App\Models\Staff;
use App\Models\TrainingPlanGroup;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()->role !== UserRole::ADMIN) {
            return redirect()->route('portal.dashboard');
        }

        $today = today();
        $activeForms = EnrollmentForm::query()
            ->with(['course:id,title,code,price', 'teacher:id,name', 'classroom:id,name,capacity'])
            ->withCount(['enrollments as confirmed_count' => fn ($query) => $query->where('status', 'registered')])
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();
        $upcomingForms = EnrollmentForm::query()
            ->with(['course:id,title,code', 'teacher:id,name', 'classroom:id,name'])
            ->withCount(['enrollments as confirmed_count' => fn ($query) => $query->where('status', 'registered')])
            ->where('is_active', true)
            ->whereDate('start_date', '>', $today)
            ->orderBy('start_date')
            ->limit(5)
            ->get();
        $nearCapacity = EnrollmentForm::query()
            ->with(['course:id,title,code'])
            ->withCount(['enrollments as confirmed_count' => fn ($query) => $query->where('status', 'registered')])
            ->where('is_active', true)
            ->get()
            ->filter(fn ($form) => $form->max_students > 0 && ($form->confirmed_count / $form->max_students) >= .8)
            ->sortByDesc(fn ($form) => $form->confirmed_count / $form->max_students)
            ->take(5)
            ->values();
        $latestEnrollments = CourseEnrollment::query()
            ->with('form.course:id,title,code')
            ->where('status', 'registered')
            ->latest('registered_at')
            ->limit(6)
            ->get();
        $todaySessions = TrainingSession::with(['group.plan.course:id,title', 'group:id,name,training_plan_id', 'classroom:id,name', 'teacher:id,name','attendances','teacherAttendance'])
            ->whereBetween('starts_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])->orderBy('starts_at')->get();
        $upcomingSessions = TrainingSession::with(['group.plan.course:id,title', 'group:id,name,training_plan_id', 'classroom:id,name', 'teacher:id,name'])
            ->where('starts_at', '>', now())->orderBy('starts_at')->limit(6)->get();
        $occupiedRoomIds = $todaySessions->pluck('classroom_id')->unique();
        $activeRooms = Classroom::where('is_active', true)->count();
        $attendanceTotal=SessionAttendance::count();$attendancePresent=SessionAttendance::whereIn('status',['present','late'])->count();
        $repeatedAbsences=Student::whereHas('attendances',fn($q)=>$q->where('status','absent'),'>=',3)->withCount(['attendances as absences_count'=>fn($q)=>$q->where('status','absent')])->orderByDesc('absences_count')->limit(8)->get();
        $groupAttendance=TrainingPlanGroup::with('plan.course')->withCount('sessions')->get()->map(function($group){$records=SessionAttendance::whereHas('session',fn($q)=>$q->where('training_plan_group_id',$group->id))->get();$rate=$records->count()?round($records->whereIn('status',['present','late'])->count()/$records->count()*100,1):null;return ['id'=>$group->id,'name'=>$group->name,'formation'=>$group->plan->course->title,'rate'=>$rate];})->filter(fn($g)=>$g['rate']!==null&&$g['rate']<75)->values();
        $completedMinutes=TrainingSession::where('status','completed')->get()->sum(fn($s)=>$s->starts_at->diffInMinutes($s->ends_at));
        $nearCompletionGroups=TrainingPlanGroup::with(['plan.course','sessions'])->get()->filter(function($group){$done=$group->sessions->where('status','completed')->sum(fn($s)=>$s->starts_at->diffInMinutes($s->ends_at))/60;return $group->plan->course->duration_hours>0&&$done/$group->plan->course->duration_hours>=.8&&$done<$group->plan->course->duration_hours;})->count();
        $salariesDue=(float)SalaryStatement::whereIn('status',['pending','partially_paid'])->sum('remaining_amount');$salariesPaid=(float)SalaryStatement::sum('amount_paid');$expenses=(float)Expense::sum('amount');$collected=(float)CourseEnrollment::whereNotNull('student_id')->sum('total_paid');

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
                    'monthly_enrollments' => CourseEnrollment::where('status', 'registered')->whereBetween('registered_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->count(),
                    'ongoing_courses' => $activeForms->count(),
                    'active_groups' => $activeForms->sum('groups_count'),
                    'monthly_expenses' => (float) Expense::whereBetween('expense_date', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])->sum('amount'),
                    'teachers_today' => $todaySessions->pluck('teacher_id')->unique()->count(),
                    'rooms_occupied' => $occupiedRoomIds->count(),
                    'rooms_available' => max(0, $activeRooms - $occupiedRoomIds->count()),
                    'waiting_registrations'=>CourseEnrollment::whereIn('status',['new','contacted','waiting','approved'])->count(),
                    'stopped_students'=>Student::where('status','stopped')->count(),
                    'completed_hours'=>round($completedMinutes/60,2),
                    'groups_near_completion'=>$nearCompletionGroups,
                    'cancelled_postponed_today'=>$todaySessions->whereIn('status',['cancelled','postponed'])->count(),
                    'students_present_today'=>$todaySessions->flatMap->attendances->whereIn('status',['present','late'])->count(),
                    'students_absent_today'=>$todaySessions->flatMap->attendances->where('status','absent')->count(),
                    'teachers_absent_today'=>$todaySessions->filter(fn($session)=>$session->teacherAttendance?->status==='absent')->count(),
                    'sessions_missing_attendance'=>$todaySessions->where('attendance_status','pending')->count(),
                    'active_employees'=>Staff::where('employment_status','active')->count(),
                    'teachers'=>Staff::whereHas('employeeType',fn($q)=>$q->where('is_teacher',true))->where('employment_status','active')->count(),
                    'other_staff'=>Staff::whereHas('employeeType',fn($q)=>$q->where('is_teacher',false))->where('employment_status','active')->count(),
                ],
                'finance' => [
                    'expected' => (float) CourseEnrollment::whereNotNull('student_id')->sum('final_price'),
                    'collected' => (float) CourseEnrollment::whereNotNull('student_id')->sum('total_paid'),
                    'remaining' => (float) CourseEnrollment::whereNotNull('student_id')->sum('remaining_balance'),
                    'overdue' => (float) CourseEnrollment::where('payment_status', 'overdue')->sum('remaining_balance'),
                    'upcoming' => (float) StudentInstallment::whereBetween('due_date', [$today, $today->copy()->addDays(30)])->whereIn('status', ['pending', 'partial'])->sum('amount'),
                    'expenses'=>$expenses,'salaries_due'=>$salariesDue,'salaries_paid'=>$salariesPaid,'net_cash_flow'=>$collected-$expenses,
                ],
                'attendance' => ['rate' => $attendanceTotal?round($attendancePresent/$attendanceTotal*100,1):null, 'recent_absences' => $repeatedAbsences, 'low_groups' => $groupAttendance, 'missing_documents' => []],
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
