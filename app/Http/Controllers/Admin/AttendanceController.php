<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendancePermission;
use App\Http\Controllers\Controller;
use App\Models\EmployeeAttendance;
use App\Models\SessionAttendance;
use App\Models\Staff;
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
        $sessions=TrainingSession::with(['group.plan.course','group.enrollments.student','classroom:id,name','teacher:id,name','teacherAttendance.actualTeacher:id,name','attendances.student'])
            ->when($request->filled('date_from'),fn($q)=>$q->whereDate('starts_at','>=',$request->date('date_from')))
            ->when($request->filled('date_to'),fn($q)=>$q->whereDate('starts_at','<=',$request->date('date_to')))
            ->when($request->filled('status'),fn($q)=>$q->whereHas('attendances',fn($a)=>$a->where('status',$request->string('status'))))
            ->latest('starts_at')->limit(150)->get();
        $studentRecords=SessionAttendance::with(['student:id,first_name,last_name,photo_path','session.group.plan.course','session.teacher:id,name','enrollment:id,level,group_number'])
            ->latest('recorded_at')->limit(300)->get();
        $employees=Staff::with('employeeType:id,name,is_teacher')->where('employment_status','active')->orderBy('last_name')->get();
        $employeeRecords=EmployeeAttendance::with(['staff.employeeType:id,name'])->latest('attendance_date')->limit(200)->get();
        $today=$sessions->filter(fn($s)=>$s->starts_at->isToday())->values();
        return Inertia::render('Admin/Attendance/Index',[
            'sessions'=>$sessions,'todaySessions'=>$today,'studentRecords'=>$studentRecords,
            'employeeRecords'=>$employeeRecords,'employees'=>$employees,
            'teachers'=>User::where('role','teacher')->where('is_active',true)->orderBy('name')->get(['id','name']),
            'filters'=>$request->only(['date_from','date_to','status']),
            'stats'=>[
                'present_today'=>$today->sum(fn($s)=>$s->attendances->whereIn('status',['present','late'])->count()),
                'absent_today'=>$today->sum(fn($s)=>$s->attendances->where('status','absent')->count()),
                'teachers_absent'=>$today->filter(fn($s)=>$s->teacherAttendance?->status==='absent')->count(),
                'missing'=>$today->where('attendance_status','pending')->count(),
                'global_rate'=>$studentRecords->count()?round($studentRecords->whereIn('status',['present','late'])->count()/$studentRecords->count()*100,1):null,
            ],
        ]);
    }

    public function students(Request $request, TrainingSession $session): RedirectResponse
    {
        Gate::authorize($session->attendance_locked_at?AttendancePermission::CORRECT_LOCKED->value:AttendancePermission::MANAGE_STUDENTS->value);
        $data=$request->validate(['records'=>['required','array'],'records.*.student_id'=>['required','integer'],'records.*.status'=>['required',Rule::in(['present','absent','late','excused','left_early'])],'records.*.is_justified'=>['nullable','boolean'],'records.*.justification'=>['nullable','string','max:2000'],'records.*.notes'=>['nullable','string','max:2000'],'correction_reason'=>['nullable','string','max:2000']]);
        $this->attendance->recordStudents($session,$data['records'],$request->user()->id,$data['correction_reason']??null,true);
        return back()->with('success','Présences étudiantes enregistrées.');
    }

    public function validateSheet(Request $request, TrainingSession $session): RedirectResponse
    {
        Gate::authorize(AttendancePermission::VALIDATE->value);$this->attendance->validate($session,$request->user()->id);return back()->with('success','Feuille validée et verrouillée.');
    }

    public function reopen(Request $request, TrainingSession $session): RedirectResponse
    {
        Gate::authorize(AttendancePermission::CORRECT_LOCKED->value);$data=$request->validate(['reason'=>['required','string','max:2000']]);$this->attendance->reopen($session,$request->user()->id,$data['reason']);return back()->with('success','Feuille rouverte. La raison est historisée.');
    }

    public function teacher(Request $request, TrainingSession $session): RedirectResponse
    {
        Gate::authorize(AttendancePermission::MANAGE_TEACHERS->value);
        $data=$request->validate(['status'=>['required',Rule::in(['present','absent','late','excused','replaced','cancelled'])],'actual_teacher_id'=>['nullable','exists:users,id'],'worked_minutes'=>['required','integer','min:0','max:1440'],'is_justified'=>['boolean'],'justification'=>['nullable','string','max:2000'],'notes'=>['nullable','string','max:2000'],'correction_reason'=>['nullable','string','max:2000']]);
        if($data['status']==='replaced'&&!($data['actual_teacher_id']??null))return back()->withErrors(['actual_teacher_id'=>'Sélectionnez le remplaçant.']);
        $record=TeacherAttendance::firstOrNew(['training_session_id'=>$session->id]);$old=$record->exists?$record->getAttributes():null;
        if($record->exists&&$record->salaryStatements()->exists()) throw ValidationException::withMessages(['attendance'=>'Ce pointage est déjà inclus dans un bulletin de salaire et ne peut plus être modifié.']);
        if($record->validated_at&&blank($data['correction_reason']??null))return back()->withErrors(['correction_reason'=>'Le motif de correction est obligatoire.']);
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
        if($record->locked_at&&blank($data['correction_reason']??null))return back()->withErrors(['correction_reason'=>'Le motif de correction est obligatoire.']);
        $record->fill([...$data,'recorded_by'=>$request->user()->id])->save();$record->histories()->create(['user_id'=>$request->user()->id,'event'=>$old?'updated':'created','old_values'=>$old,'new_values'=>$record->getAttributes(),'reason'=>$data['correction_reason']??null,'occurred_at'=>now()]);
        return back()->with('success','Pointage employé enregistré.');
    }
}
