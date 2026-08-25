<?php
namespace App\Http\Controllers;
use App\Models\CourseEnrollment;
use App\Models\PortalNotification;
use App\Models\SessionAttendance;
use App\Models\Student;
use App\Models\TrainingPlanGroup;
use App\Models\TrainingSession;
use App\Models\StudentPayment;
use App\Models\CompanySetting;
use App\Models\Badge;
use App\Models\StudentInstallment;
use App\Models\StudentObservation;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Services\NotificationDispatcher;
use App\Services\AttendanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PortalController extends Controller
{
 public function dashboard(Request $request): Response
 {
  $user=$request->user();$this->ensureReminders($user);$notifications=$user->portalNotifications()->limit(8)->get();
  if($user->schoolParent){
   $profile=$user->schoolParent->load(['students.enrollments'=>fn($query)=>$query->with(['form.course','trainingPlanGroup.plan.course','payments','installments'])]);$allUpcoming=collect();$paid=0;$due=0;$recorded=0;$present=0;
   foreach($profile->students as $child){$enrollments=$child->enrollments;$sessions=$this->sessionsForEnrollments($enrollments);$upcoming=$sessions->where('starts_at','>',now())->take(3)->values();$attendance=$child->attendances()->get();$childPaid=(float)$enrollments->sum('total_paid');$childBalance=(float)$enrollments->sum('remaining_balance');$childPresent=$attendance->whereIn('status',['present','late'])->count();$child->setAttribute('portal_summary',['formations'=>$enrollments->where('status','registered')->count(),'paid'=>$childPaid,'balance'=>max(0,$childBalance),'attendance_rate'=>$attendance->count()?round($childPresent/$attendance->count()*100,1):null,'upcoming'=>$upcoming]);$paid+=$childPaid;$due+=$childPaid+$childBalance;$recorded+=$attendance->count();$present+=$childPresent;$allUpcoming=$allUpcoming->concat($upcoming);}
   return Inertia::render('Portal/Dashboard',['kind'=>'parent','profile'=>$profile,'today'=>[],'upcoming'=>$allUpcoming->sortBy('starts_at')->unique('id')->take(8)->values(),'groups'=>[],'students'=>[],'notifications'=>$notifications,'summary'=>['children'=>$profile->students->count(),'paid'=>$paid,'balance'=>max(0,$due-$paid),'attendance_rate'=>$recorded?round($present/$recorded*100,1):null]]);
  }
  if($user->student)return $this->studentPage($user->student,'dashboard',$notifications);
  $sessions=$this->teacherSessions($user->id);$students=$this->enrollmentsForGroups($this->activeTeacherGroups($user->id))->pluck('student')->filter()->unique('id')->values();
  $hours=(float)TrainingSession::where('teacher_id',$user->id)->where('status','completed')->get()->sum(fn($s)=>$s->starts_at->diffInMinutes($s->ends_at))/60;
  return Inertia::render('Portal/Dashboard',['kind'=>$user->staff?->is_teacher?'teacher':'employee','profile'=>$user->staff,'today'=>$sessions->whereBetween('starts_at',[today()->startOfDay(),today()->endOfDay()])->values(),'upcoming'=>$sessions->where('starts_at','>',now())->take(12)->values(),'groups'=>[],'students'=>$students,'notifications'=>$notifications,'summary'=>['completed_hours'=>round($hours,2)]]);
 }

 public function section(Request $request,string $section): Response
 {
  abort_unless(in_array($section,['formation','planning','attendance','payments','documents','notifications','students'],true),404);$user=$request->user();
  if($user->schoolParent){$children=$user->schoolParent->students()->with(['enrollments'=>fn($q)=>$q->where('status','registered')->with(['form.course','trainingPlanGroup.plan.course']),'attendances.session','payments.enrollment.form.course','payments.enrollment.trainingPlanGroup.plan.course'])->get();foreach($children as $child)$child->setAttribute('portal_sessions',$this->sessionsForEnrollments($child->enrollments));return Inertia::render('Portal/Section',['kind'=>'parent','section'=>$section,'children'=>$children,'data'=>['notifications'=>$user->portalNotifications()->get()]]);}
  if($user->student)return $this->studentPage($user->student,$section,$user->portalNotifications()->get(),'Portal/Section');
  abort_unless($user->staff,403);$sessions=$this->teacherSessions($user->id);$students=$this->enrollmentsForGroups($this->activeTeacherGroups($user->id))->unique('student_id')->values();
  $upcomingSessions=$pastSessions=null;if($section==='planning'){$upcomingSessions=$this->paginateCollection($sessions->filter(fn($session)=>$session->ends_at->gte(now())&&!in_array($session->status,['completed','cancelled']))->values(),$request,'upcoming_page');$pastSessions=$this->paginateCollection($sessions->reject(fn($session)=>$session->ends_at->gte(now())&&!in_array($session->status,['completed','cancelled']))->sortByDesc('starts_at')->values(),$request,'past_page');}
  $archivedStudents=$section==='students'?$this->enrollmentsForGroups($this->archivedTeacherGroups($user->id))->unique('student_id')->values():collect();
  $archivedStudents->each(fn($enrollment)=>$enrollment->student?->makeHidden(['photo_path','photo_url','phone','parent_phone']));
  if($section==='students'){$students=$this->paginateCollection($students,$request,'active_page');$archivedStudents=$this->paginateCollection($archivedStudents,$request,'history_page');}
  return Inertia::render('Portal/Section',['kind'=>$user->staff->is_teacher?'teacher':'employee','section'=>$section,'data'=>['sessions'=>$sessions,'upcoming_sessions'=>$upcomingSessions,'past_sessions'=>$pastSessions,'students'=>$students,'archived_students'=>$archivedStudents,'notifications'=>$user->portalNotifications()->get(),'can_view_student_folders'=>(bool)$user->staff->can_view_student_folders]]);
 }

 public function attendance(Request $request,TrainingSession $session,NotificationDispatcher $notifications): RedirectResponse
 {
  abort_unless($session->teacher_id===$request->user()->id,403);$data=$request->validate(['records'=>['required','array'],'records.*.student_id'=>['required','exists:students,id'],'records.*.status'=>['required',Rule::in(['present','absent','late','excused','left_early'])],'records.*.justification'=>['nullable','string','max:2000'],'records.*.notes'=>['nullable','string','max:1000']]);
  app(AttendanceService::class)->recordStudents($session,$data['records'],$request->user()->id);
  foreach($data['records'] as $record)if($record['status']==='absent'&&SessionAttendance::where('student_id',$record['student_id'])->where('status','absent')->count()>=3){$student=Student::with(['user','parents.user'])->find($record['student_id']);foreach(collect([$student->user])->merge($student->parents->pluck('user'))->filter()->unique('id') as $recipient)$notifications->send($recipient,'student.repeated_absence','Absences répétées','Trois absences ou plus ont été enregistrées.',$student);}
  return back()->with('success','Présences enregistrées.');
 }

 public function read(Request $request,PortalNotification $notification): RedirectResponse {abort_unless($notification->recipient_id===$request->user()->id,403);$notification->update(['read_at'=>now()]);return back();}
 public function readAll(Request $request): RedirectResponse {$request->user()->portalNotifications()->whereNull('read_at')->update(['read_at'=>now()]);return back();}
 public function receipt(Request $request,StudentPayment $payment): HttpResponse {$user=$request->user();$allowed=$user->student?->id===$payment->student_id||($user->schoolParent?->students()->whereKey($payment->student_id)->exists()??false);abort_unless($allowed,403);$payment->load(['student','enrollment.form.course','enrollment.trainingPlanGroup.plan.course','recorder']);return Pdf::loadView('admin.finance.receipt',['payment'=>$payment,'school'=>CompanySetting::current(),'currency'=>config('app.currency_symbol')])->download($payment->reference.'.pdf');}

 public function studentFolder(Request $request,Student $student): Response
 {
  $user=$request->user();abort_unless($user->role===UserRole::TEACHER&&$user->staff?->can_view_student_folders,403,'La consultation des dossiers étudiants n’est pas autorisée.');
  $activePlan=fn($query)=>$query->whereIn('status',['scheduled','in_progress'])->where(fn($query)=>$query->where('teacher_id',$user->id)->orWhereHas('teacherAccesses',fn($access)=>$access->where('teacher_id',$user->id)));
  abort_unless($student->enrollments()->where('status','registered')->whereHas('trainingPlanGroup.plan',$activePlan)->exists(),403,'Cet étudiant ne fait pas partie de vos planifications actives.');
  $student->load(['enrollments'=>fn($query)=>$query->where('status','registered')->whereHas('trainingPlanGroup.plan',$activePlan)->with(['form.course','trainingPlanGroup.plan.level.course']),'badges.template','certificates.enrollment.form.course','histories.user:id,name','user:id,email,is_active','observations'=>fn($query)=>$query->whereNull('parent_id')->with(['author:id,name,role','replies.author:id,name,role']),'attendances'=>fn($query)=>$query->whereHas('session.group.plan',$activePlan)->with(['session.group.plan.level.course','session.teacher:id,name'])]);
  $expected=TrainingSession::whereHas('group.enrollments',fn($query)=>$query->where('student_id',$student->id)->where('status','registered'))->whereHas('group.plan',$activePlan)->count();$records=$student->attendances;$present=$records->whereIn('status',['present','late'])->count();$consecutive=0;
  foreach($records->sortByDesc(fn($attendance)=>$attendance->session?->starts_at) as $record){if($record->status!=='absent')break;$consecutive++;}
  $rate=$expected?round($present/$expected*100,1):null;$student->setAttribute('attendance_stats',['expected'=>$expected,'recorded'=>$records->count(),'present'=>$present,'absent'=>$records->where('status','absent')->count(),'late'=>$records->where('status','late')->count(),'excused'=>$records->where('status','excused')->count(),'rate'=>$rate,'consecutive_absences'=>$consecutive,'warning'=>$consecutive>=config('attendance.consecutive_absence_warning',2)||($rate!==null&&$rate<config('attendance.warning_threshold',75))]);
  return Inertia::render('Admin/Students/Show',['student'=>$student,'statuses'=>collect(StudentStatus::cases())->map(fn($status)=>$status->value),'readOnly'=>true,'teacherView'=>true]);
 }

 public function childFolder(Request $request,Student $student): Response
 {
  $parent=$request->user()->schoolParent;abort_unless($request->user()->role===UserRole::PARENT&&$parent&&$parent->students()->whereKey($student->id)->exists(),403,'Cet enfant n’est pas associé à votre compte.');
  $student->load(['enrollments.form.course','enrollments.trainingPlanGroup.plan.level.course','enrollments.installments','enrollments.payments.recorder:id,name','badges.template','certificates.enrollment.form.course','histories.user:id,name','files','user:id,email,is_active','observations'=>fn($query)=>$query->whereNull('parent_id')->with(['author:id,name,role','replies.author:id,name,role']),'attendances.session.group.plan.level.course','attendances.session.teacher:id,name']);
  $expected=TrainingSession::whereHas('group.enrollments',fn($query)=>$query->where('student_id',$student->id)->where('status','registered'))->count();$records=$student->attendances;$present=$records->whereIn('status',['present','late'])->count();$consecutive=0;
  foreach($records->sortByDesc(fn($attendance)=>$attendance->session?->starts_at) as $record){if($record->status!=='absent')break;$consecutive++;}
  $rate=$expected?round($present/$expected*100,1):null;$student->setAttribute('attendance_stats',['expected'=>$expected,'recorded'=>$records->count(),'present'=>$present,'absent'=>$records->where('status','absent')->count(),'late'=>$records->where('status','late')->count(),'excused'=>$records->where('status','excused')->count(),'rate'=>$rate,'consecutive_absences'=>$consecutive,'warning'=>$consecutive>=config('attendance.consecutive_absence_warning',2)||($rate!==null&&$rate<config('attendance.warning_threshold',75))]);
  return Inertia::render('Admin/Students/Show',['student'=>$student,'statuses'=>collect(StudentStatus::cases())->map(fn($status)=>$status->value),'readOnly'=>true,'parentView'=>true]);
 }

 public function storeObservation(Request $request,Student $student,NotificationDispatcher $notifications): RedirectResponse
 {
  $user=$request->user();$parent=$user->schoolParent;$admin=$user->role===UserRole::ADMIN;$teacher=$user->role===UserRole::TEACHER&&$user->staff?->can_view_student_folders;
  $parentOwns=$parent?->students()->whereKey($student->id)->exists()??false;
  $teacherHasStudent=$teacher&&$student->enrollments()->where('status','registered')->whereHas('trainingPlanGroup.plan',fn($plan)=>$plan->whereIn('status',['scheduled','in_progress'])->where(fn($plan)=>$plan->where('teacher_id',$user->id)->orWhereHas('teacherAccesses',fn($access)=>$access->where('teacher_id',$user->id))))->exists();
  abort_unless($admin||$parentOwns||$teacherHasStudent,403);
  $data=$request->validate(['message'=>['required','string','max:5000'],'parent_id'=>['nullable','integer','exists:student_observations,id']]);
  $thread=null;if($data['parent_id']??null){$thread=StudentObservation::where('student_id',$student->id)->whereNull('parent_id')->findOrFail($data['parent_id']);}else abort_unless($admin||$teacherHasStudent,403,'Seul un enseignant ou un administrateur peut ouvrir une observation.');
  $observation=$student->observations()->create(['author_id'=>$user->id,'parent_id'=>$thread?->id,'message'=>$data['message']]);
  if($parentOwns){$author=$thread?->author;if($author){$url=$author->role===UserRole::ADMIN?'/admin/students/'.$student->id:'/portal/students/'.$student->id;$notifications->send($author,'observation.parent_replied','Réponse d’un parent',$user->name.' a répondu à une observation concernant '.$student->full_name.'.',$observation,['url'=>$url]);}}
  else foreach($student->parents()->with('user')->get()->pluck('user')->filter()->unique('id') as $recipient)$notifications->send($recipient,$admin?'observation.admin_added':'observation.teacher_added','Nouvelle observation','Une observation a été ajoutée au dossier de '.$student->full_name.' par '.$user->name.'.',$observation,['url'=>'/portal/children/'.$student->id]);
  return back()->with('success',$thread?'Réponse envoyée.':'Observation ajoutée et parents notifiés.');
 }

 private function teacherSessions(int $userId){$sessions=TrainingSession::with(['group.plan.level.course','group.enrollments.student','classroom.site','attendances','teacherAttendance.salaryStatements:id,status,period_end,amount_paid,remaining_amount'])->where(fn($query)=>$query->where('teacher_id',$userId)->orWhereHas('teacherAttendance',fn($attendance)=>$attendance->where('actual_teacher_id',$userId)))->orderBy('starts_at')->get();return $sessions->each(function($session)use($userId){$attendance=$session->teacherAttendance;$statement=$attendance?->salaryStatements?->sortByDesc('period_end')->first();$session->setAttribute('portal_details',['student_count'=>$session->group?->enrollments?->count()??0,'attendance_status'=>$attendance?->status??'not_recorded','attendance_validated'=>(bool)$attendance?->validated_at,'worked_minutes'=>(int)($attendance?->worked_minutes??0),'is_replacement'=>$attendance&&$attendance->actual_teacher_id===$userId&&$attendance->scheduled_teacher_id!==$userId,'salary_status'=>!$statement?'not_calculated':((float)$statement->remaining_amount<=0?'paid':((float)$statement->amount_paid>0?'partially_paid':'unpaid')),'salary_statement_id'=>$statement?->id]);});}
 private function activeTeacherGroups(int $userId){return $this->teacherGroupsByPlanStatus($userId,['scheduled','in_progress']);}
 private function archivedTeacherGroups(int $userId){return $this->teacherGroupsByPlanStatus($userId,['completed','cancelled']);}
 private function teacherGroupsByPlanStatus(int $userId,array $statuses){return TrainingPlanGroup::with(['plan.course','sessions.classroom'])->whereHas('plan',fn($query)=>$query->whereIn('status',$statuses)->where(fn($query)=>$query->where('teacher_id',$userId)->orWhereHas('teacherAccesses',fn($access)=>$access->where('teacher_id',$userId))))->get();}
 private function enrollmentsForGroups($groups){if($groups->isEmpty())return collect();return CourseEnrollment::with(['student','form.course','trainingPlanGroup.plan.course'])->where('status','registered')->whereIn('training_plan_group_id',$groups->pluck('id'))->get();}
 private function paginateCollection($items,Request $request,string $pageName){$page=max(1,$request->integer($pageName,1));return new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($page,12)->values(),$items->count(),12,$page,['path'=>$request->url(),'pageName'=>$pageName,'query'=>$request->query()]);}
 private function studentPage(Student $student,string $section,$notifications,string $view='Portal/Dashboard'): Response {$enrollments=$student->enrollments()->where('status','registered')->with(['form.course','trainingPlanGroup.plan.course','payments.recorder:id,name','installments'])->get();$sessions=$this->sessionsForEnrollments($enrollments);return Inertia::render($view,['kind'=>'student','section'=>$section,'profile'=>$student->load(['badges.template','files','attendances.session']),'data'=>['enrollments'=>$enrollments,'sessions'=>$sessions,'notifications'=>$notifications],'today'=>$sessions->whereBetween('starts_at',[today()->startOfDay(),today()->endOfDay()])->values(),'upcoming'=>$sessions->where('starts_at','>',now())->take(12)->values(),'groups'=>[],'students'=>[],'notifications'=>$notifications,'summary'=>[]]);}
 private function sessionsForEnrollments($enrollments){if($enrollments->isEmpty())return collect();return TrainingSession::with(['group.plan.course','classroom'])->whereIn('training_plan_group_id',$enrollments->pluck('training_plan_group_id')->filter()->unique())->orderBy('starts_at')->get();}
 private function ensureReminders($user): void {$students=$user->student?collect([$user->student]):($user->schoolParent?$user->schoolParent->students:collect());foreach($students as $student){$installments=StudentInstallment::with(['enrollment.form.course','enrollment.trainingPlanGroup.plan.course'])->whereHas('enrollment',fn($q)=>$q->where('student_id',$student->id))->whereIn('status',['pending','partial','overdue'])->whereDate('due_date','<=',today()->addDays(7))->get();foreach($installments as $item){$title=$item->enrollment->form?->course?->title??$item->enrollment->trainingPlanGroup?->plan?->course?->title??'Formation';$this->notifyOnce($user,$item->due_date->isPast()?'payment.overdue':'payment.due',$item,$item->due_date->isPast()?'Paiement en retard':'Paiement bientôt dû','Échéance '.$title.' du '.$item->due_date->format('d/m/Y').'.');}foreach($student->enrollments()->where('status','registered')->with('form')->get() as $enrollment)if($enrollment->form?->start_date?->between(today(),today()->addDays(7)))$this->notifyOnce($user,'formation.starting',$enrollment,'Formation prochainement','La formation commence le '.$enrollment->form->start_date->format('d/m/Y').'.');} $owner=$user->student?:$user->staff;if($owner){$badge=$owner->badges()->where('status','active')->whereBetween('expiration_date',[today(),today()->addDays(30)])->first();if($badge)$this->notifyOnce($user,'badge.expiring',$badge,'Carte bientôt expirée','Votre carte expire le '.$badge->expiration_date->format('d/m/Y').'.');}}
 private function notifyOnce($user,string $type,$related,string $title,string $message): void {if(!PortalNotification::where('recipient_id',$user->id)->where('type',$type)->where('related_type',$related->getMorphClass())->where('related_id',$related->id)->whereDate('occurred_at',today())->exists())app(NotificationDispatcher::class)->send($user,$type,$title,$message,$related);}
}
