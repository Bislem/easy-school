<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendancePermission;
use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendance;
use App\Models\SessionAttendance;
use App\Models\Staff;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    public function index(Request $request): Response
    {
        Gate::authorize(AttendancePermission::VIEW->value);
        $type = $request->string('person_type')->toString() === 'employee' ? 'employee' : 'student';
        $personId = $request->integer('person_id') ?: null;
        $status = $request->string('status')->trim()->toString();
        $from = $request->date('date_from');
        $to = $request->date('date_to');

        $students = Student::query()->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'photo_path', 'status']);
        $employees = Staff::with(['employeeType:id,name,is_teacher', 'user:id,name'])
            ->orderBy('last_name')->orderBy('first_name')->get();
        $selectedPerson = $type === 'student'
            ? ($personId ? $students->firstWhere('id', $personId) : null)
            : ($personId ? $employees->firstWhere('id', $personId) : null);
        $records = collect();

        if ($selectedPerson instanceof Student) {
            $records = SessionAttendance::with(['session.group.plan.level.course', 'session.group.plan', 'session.classroom:id,name,code', 'session.teacher:id,name'])
                ->where('student_id', $selectedPerson->id)
                ->when($from, fn ($query) => $query->whereHas('session', fn ($session) => $session->whereDate('starts_at', '>=', $from)))
                ->when($to, fn ($query) => $query->whereHas('session', fn ($session) => $session->whereDate('starts_at', '<=', $to)))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->get()->map(fn (SessionAttendance $record) => [
                    'id' => $record->id, 'kind' => 'student', 'session_id' => $record->training_session_id,
                    'date' => $record->session?->starts_at?->toDateString(), 'starts_at' => $record->session?->starts_at?->toIso8601String(),
                    'ends_at' => $record->session?->ends_at?->toIso8601String(), 'status' => $record->status,
                    'worked_minutes' => null, 'is_justified' => $record->is_justified, 'justification' => $record->justification,
                    'notes' => $record->notes, 'formation' => $record->session?->group?->plan?->level?->course?->title,
                    'planning' => $record->session?->group?->plan?->title, 'group' => $record->session?->group?->name,
                    'room' => $record->session?->classroom?->name, 'teacher' => $record->session?->teacher?->name,
                    'session' => $record->session?->title,
                ]);
        } elseif ($selectedPerson instanceof Staff && $selectedPerson->employeeType?->is_teacher && $selectedPerson->user_id) {
            $records = TeacherAttendance::with(['session.group.plan.level.course', 'session.group.plan', 'session.classroom:id,name,code', 'scheduledTeacher:id,name', 'actualTeacher:id,name'])
                ->where(fn ($query) => $query->where('scheduled_teacher_id', $selectedPerson->user_id)->orWhere('actual_teacher_id', $selectedPerson->user_id))
                ->when($from, fn ($query) => $query->whereHas('session', fn ($session) => $session->whereDate('starts_at', '>=', $from)))
                ->when($to, fn ($query) => $query->whereHas('session', fn ($session) => $session->whereDate('starts_at', '<=', $to)))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->get()->map(fn (TeacherAttendance $record) => [
                    'id' => $record->id, 'kind' => 'teacher', 'session_id' => $record->training_session_id,
                    'date' => $record->session?->starts_at?->toDateString(), 'starts_at' => $record->session?->starts_at?->toIso8601String(),
                    'ends_at' => $record->session?->ends_at?->toIso8601String(), 'status' => $record->status,
                    'worked_minutes' => $record->worked_minutes, 'is_justified' => $record->is_justified, 'justification' => $record->justification,
                    'notes' => $record->notes, 'formation' => $record->session?->group?->plan?->level?->course?->title,
                    'planning' => $record->session?->group?->plan?->title, 'group' => $record->session?->group?->name,
                    'room' => $record->session?->classroom?->name, 'teacher' => $record->actualTeacher?->name ?? $record->scheduledTeacher?->name,
                    'session' => $record->session?->title, 'actual_teacher_id' => $record->actual_teacher_id,
                ]);
        } elseif ($selectedPerson instanceof Staff) {
            $records = EmployeeAttendance::where('staff_id', $selectedPerson->id)
                ->when($from, fn ($query) => $query->whereDate('attendance_date', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('attendance_date', '<=', $to))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->get()->map(fn (EmployeeAttendance $record) => [
                    'id' => $record->id, 'kind' => 'employee', 'session_id' => null,
                    'date' => $record->attendance_date?->toDateString(), 'starts_at' => null, 'ends_at' => null,
                    'status' => $record->status, 'worked_minutes' => $record->worked_minutes,
                    'is_justified' => $record->is_justified, 'justification' => $record->justification,
                    'notes' => $record->notes, 'formation' => null, 'planning' => null, 'group' => null,
                    'room' => null, 'teacher' => null, 'session' => null,
                    'check_in' => $record->check_in, 'check_out' => $record->check_out,
                ]);
        }

        $records = $records->filter(fn ($record) => filled($record['date']))
            ->sortByDesc(fn ($record) => $record['starts_at'] ?? $record['date'])->values();
        $present = $records->whereIn('status', ['present', 'late', 'replaced'])->count();

        return Inertia::render('Admin/Attendance/Index', [
            'personType' => $type, 'selectedPerson' => $selectedPerson, 'students' => $students, 'employees' => $employees,
            'records' => $records, 'teachers' => User::where('role', 'teacher')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => ['person_type' => $type, 'person_id' => $personId, 'date_from' => $request->input('date_from'), 'date_to' => $request->input('date_to'), 'status' => $status],
            'stats' => ['total' => $records->count(), 'present' => $present, 'absent' => $records->whereIn('status', ['absent', 'excused', 'leave'])->count(),
                'late' => $records->where('status', 'late')->count(), 'rate' => $records->count() ? round($present / $records->count() * 100, 1) : null,
                'worked_hours' => round($records->sum('worked_minutes') / 60, 2)],
        ]);
    }

    public function students(Request $request, TrainingSession $session): RedirectResponse
    {
        Gate::authorize($session->attendance_status !== 'pending'?AttendancePermission::CORRECT_LOCKED->value:AttendancePermission::MANAGE_STUDENTS->value);
        $data=$request->validate(['records'=>['required','array'],'records.*.student_id'=>['required','integer'],'records.*.status'=>['required',Rule::in(['present','absent','late','excused','left_early'])],'records.*.is_justified'=>['nullable','boolean'],'records.*.justification'=>['nullable','string','max:2000'],'records.*.notes'=>['nullable','string','max:2000'],'correction_reason'=>['nullable','string','max:2000'],'validate_session'=>['sometimes','boolean']]);
        if (($data['validate_session'] ?? false) && ! $session->attendance_locked_at) Gate::authorize(AttendancePermission::VALIDATE->value);
        $this->attendance->recordStudents($session,$data['records'],$request->user()->id,$data['correction_reason']??null,true);
        if (($data['validate_session'] ?? false) && ! $session->attendance_locked_at) {
            $this->attendance->validate($session, $request->user()->id);
            return back()->with('success','Présences et séance validées définitivement. Le formateur est marqué présent.');
        }
        return back()->with('success','Présences étudiantes enregistrées.');
    }

    public function validateSheet(Request $request, TrainingSession $session): RedirectResponse
    {
        Gate::authorize(AttendancePermission::VALIDATE->value);$this->attendance->validate($session,$request->user()->id);return back()->with('success','Feuille validée et verrouillée.');
    }

    public function teacher(Request $request, TrainingSession $session): RedirectResponse
    {
        Gate::authorize(AttendancePermission::MANAGE_TEACHERS->value);
        $data=$request->validate(['status'=>['required',Rule::in(['present','absent','late','excused','replaced','cancelled'])],'actual_teacher_id'=>['nullable','exists:users,id'],'worked_minutes'=>['required','integer','min:0','max:1440'],'is_justified'=>['boolean'],'justification'=>['nullable','string','max:2000'],'notes'=>['nullable','string','max:2000'],'correction_reason'=>['nullable','string','max:2000']]);
        if($data['status']==='replaced'&&!($data['actual_teacher_id']??null))return back()->withErrors(['actual_teacher_id'=>'Sélectionnez le remplaçant.']);
        $record=TeacherAttendance::firstOrNew(['training_session_id'=>$session->id]);$old=$record->exists?$record->getAttributes():null;
        if($record->exists&&$record->salaryStatements()->exists()) throw ValidationException::withMessages(['attendance'=>'Ce pointage est déjà inclus dans un bulletin de salaire et ne peut plus être modifié.']);
        if($record->exists&&blank($data['correction_reason']??null))return back()->withErrors(['correction_reason'=>'Le justificatif écrit de correction est obligatoire.']);
        $record->fill([...$data,'scheduled_teacher_id'=>$session->teacher_id,'actual_teacher_id'=>$data['actual_teacher_id']??($data['status']==='present'||$data['status']==='late'?$session->teacher_id:null),'recorded_by'=>$request->user()->id,'validated_at'=>now(),'validated_by'=>$request->user()->id])->save();
        $record->histories()->create(['user_id'=>$request->user()->id,'event'=>$old?'updated':'created','old_values'=>$old,'new_values'=>$record->getAttributes(),'reason'=>$data['correction_reason']??null,'occurred_at'=>now()]);
        return back()->with('success','Pointage enseignant enregistré.');
    }

    public function teachersBulk(Request $request): RedirectResponse
    {
        Gate::authorize(AttendancePermission::MANAGE_TEACHERS->value);
        $data=$request->validate(['session_ids'=>['required','array','min:1'],'session_ids.*'=>['integer','distinct','exists:training_sessions,id'],'status'=>['required',Rule::in(['present','absent','late','excused','cancelled'])],'justification'=>['nullable','string','max:2000']]);
        $sessions=TrainingSession::whereIn('id',$data['session_ids'])->get();
        foreach($sessions as $session){
            $record=TeacherAttendance::firstOrNew(['training_session_id'=>$session->id]);
            if($record->validated_at)continue;
            $old=$record->exists?$record->getAttributes():null;$worked=in_array($data['status'],['present','late'],true)?$session->starts_at->diffInMinutes($session->ends_at):0;
            $record->fill(['scheduled_teacher_id'=>$session->teacher_id,'actual_teacher_id'=>in_array($data['status'],['present','late'],true)?$session->teacher_id:null,'status'=>$data['status'],'worked_minutes'=>$worked,'is_justified'=>$data['status']==='excused','justification'=>$data['justification']??null,'recorded_by'=>$request->user()->id,'validated_at'=>now(),'validated_by'=>$request->user()->id])->save();
            $record->histories()->create(['user_id'=>$request->user()->id,'event'=>'bulk_'.($old?'updated':'created'),'old_values'=>$old,'new_values'=>$record->getAttributes(),'reason'=>$data['justification']??'Action groupée','occurred_at'=>now()]);
        }
        return back()->with('success',$sessions->count().' pointage(s) enseignant traité(s).');
    }

    public function employee(Request $request): RedirectResponse
    {
        Gate::authorize(AttendancePermission::MANAGE_EMPLOYEES->value);
        $data=$request->validate(['staff_id'=>['required','exists:staff,id'],'attendance_date'=>['required','date'],'status'=>['required',Rule::in(['present','absent','late','excused','leave'])],'check_in'=>['nullable','date_format:H:i'],'check_out'=>['nullable','date_format:H:i'],'worked_minutes'=>['required','integer','min:0','max:1440'],'is_justified'=>['boolean'],'justification'=>['nullable','string','max:2000'],'notes'=>['nullable','string','max:2000'],'correction_reason'=>['nullable','string','max:2000']]);
        $record=EmployeeAttendance::firstOrNew(['staff_id'=>$data['staff_id'],'attendance_date'=>$data['attendance_date']]);$old=$record->exists?$record->getAttributes():null;
        if($record->exists&&$record->salaryStatements()->exists()) throw ValidationException::withMessages(['attendance'=>'Ce pointage est déjà inclus dans un bulletin de salaire et ne peut plus être modifié.']);
        if($record->exists&&blank($data['correction_reason']??null))return back()->withErrors(['correction_reason'=>'Le justificatif écrit de correction est obligatoire.']);
        $record->fill([...$data,'recorded_by'=>$request->user()->id])->save();$record->histories()->create(['user_id'=>$request->user()->id,'event'=>$old?'updated':'created','old_values'=>$old,'new_values'=>$record->getAttributes(),'reason'=>$data['correction_reason']??null,'occurred_at'=>now()]);
        return back()->with('success','Pointage employé enregistré.');
    }
}
