<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{AcademicYear, ReportCard, SchoolCycle, SchoolGroup, SchoolLevel, StudentAcademicEnrollment};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Services\ReportCardCalculationService;
use App\Services\ReportCardValidationService;
class ReportCardsController extends Controller {
    public function index(Request $request) {
        $yearId = $request->integer('academic_year_id') ?: $request->session()->get('academic_year_id');
        $year = AcademicYear::find($yearId) ?: AcademicYear::where('status','active')->latest('start_date')->first();
        $filters=$request->only(['academic_year_id','period_key','cycle_id','specialization','level_id','group_id','search','status','average_min','average_max','problematic']); $period=$filters['period_key']??'trimester_1';
        abort_unless(isset(config('report_cards.periods')[$period]), 422, 'Période invalide.');
        $query=ReportCard::query()->when($year,fn($q)=>$q->where('academic_year_id',$year->id))->where('period_key',$period)
            ->when($filters['cycle_id']??null,fn($q,$id)=>$q->whereHas('level',fn($level)=>$level->where('school_cycle_id',$id)))
            ->when($filters['specialization']??null,fn($q,$value)=>$q->whereHas('level',fn($level)=>$level->where('specialization',$value)))
            ->when($filters['level_id']??null,fn($q,$id)=>$q->where('school_level_id',$id))->when($filters['group_id']??null,fn($q,$id)=>$q->where('school_group_id',$id))->when($filters['status']??null,fn($q,$s)=>$q->where('status',$s))
            ->when($filters['average_min']??null,fn($q,$value)=>$q->where('general_average','>=',$value))->when($filters['average_max']??null,fn($q,$value)=>$q->where('general_average','<=',$value))
            ->when($request->boolean('problematic'),fn($q)=>$q->where(fn($problem)=>$problem->whereNull('general_average')->orWhere('source_data_changed',true)->orWhereHas('subjects',fn($subject)=>$subject->whereNull('average'))))
            ->when($filters['search']??null,function($q,$value){$term=trim((string)$value);$q->whereHas('student',fn($student)=>$student->where(fn($name)=>$name->where('first_name','like',"%{$term}%")->orWhere('last_name','like',"%{$term}%")->orWhere('registration_number','like',"%{$term}%")));});
        $summaryQuery=(clone $query);
        $cards=$query->with(['student:id,first_name,last_name,registration_number','subjects:id,report_card_id,average'])->orderBy('rank')->orderBy('student_id')->paginate(25)->withQueryString();
        $levels=SchoolLevel::where('is_active',true)->with('cycle:id,name')->orderBy('sort_order')->get(['id','name','specialization','school_cycle_id']);
        return Inertia::render('Admin/ReportCards/Index', ['academicYears'=>AcademicYear::orderByDesc('start_date')->get(['id','name']), 'academicYear'=>$year, 'periods'=>config('report_cards.periods'), 'cycles'=>SchoolCycle::where('is_active',true)->orderBy('sort_order')->get(['id','name']), 'levels'=>$levels, 'specializations'=>$levels->pluck('specialization')->filter()->unique()->values(), 'groups'=>SchoolGroup::when($year,fn($q)=>$q->where('academic_year_id',$year->id))->where('is_active',true)->orderBy('name')->get(['id','name','school_level_id']), 'cards'=>$cards, 'summary'=>['total'=>(clone $summaryQuery)->count(),'draft'=>(clone $summaryQuery)->where('status','draft')->count(),'validated'=>(clone $summaryQuery)->where('status','validated')->count(),'published'=>(clone $summaryQuery)->where('status','published')->count(),'locked'=>(clone $summaryQuery)->where('status','locked')->count(),'problematic'=>(clone $summaryQuery)->where(fn($q)=>$q->whereNull('general_average')->orWhere('source_data_changed',true)->orWhereHas('subjects',fn($s)=>$s->whereNull('average')))->count()], 'filters'=>$filters]);
    }
    public function recalculate(\App\Models\ReportCard $reportCard, ReportCardCalculationService $service) {
        abort_unless((int)$reportCard->tenant_id === (int)app(\App\Tenancy\TenantContext::class)->id(), 404);
        $result = $reportCard->period_key === 'annual' ? $service->calculateAnnualReport($reportCard) : $service->recalculateReportCard($reportCard);
        return back()->with('success', empty($result['errors']) ? 'Bulletin recalculé.' : $result['errors'][0]['message']);
    }
    public function validateCard(ReportCard $reportCard, ReportCardValidationService $service) { abort_unless((int)$reportCard->tenant_id === (int)app(\App\Tenancy\TenantContext::class)->id(),404); $result=$service->validate($reportCard,auth()->id()); return back()->with($result['ready']?'success':'error',$result['ready']?'Bulletin validé.':'Le bulletin contient encore des anomalies bloquantes.'); }
    public function publish(ReportCard $reportCard, ReportCardValidationService $service) { abort_unless((int)$reportCard->tenant_id === (int)app(\App\Tenancy\TenantContext::class)->id(),404); $service->transition($reportCard,'published',auth()->id()); return back()->with('success','Bulletin publié.'); }
    public function lock(ReportCard $reportCard, ReportCardValidationService $service) { abort_unless((int)$reportCard->tenant_id === (int)app(\App\Tenancy\TenantContext::class)->id(),404); $service->transition($reportCard,'locked',auth()->id()); return back()->with('success','Bulletin verrouillé.'); }
    public function reopen(Request $request, ReportCard $reportCard, ReportCardValidationService $service) { $reason=$request->validate(['reason'=>'required|string|min:10|max:1000'])['reason']; abort_unless((int)$reportCard->tenant_id === (int)app(\App\Tenancy\TenantContext::class)->id(),404); $service->transition($reportCard,'draft',auth()->id(),$reason); return back()->with('success','Bulletin rouvert.'); }
    public function generate(Request $request, \App\Services\ReportCardInitializationService $service) { [$group,$period]=$this->groupContext($request); $created=0; foreach(StudentAcademicEnrollment::where('school_group_id',$group->id)->where('academic_year_id',$group->academic_year_id)->get() as $enrollment){if(!ReportCard::where('student_academic_enrollment_id',$enrollment->id)->where('period_key',$period)->exists()){$service->initialize($enrollment,$period);$created++;}} return back()->with('success',"$created bulletin(s) généré(s)."); }
    public function recalculateGroup(Request $request, ReportCardCalculationService $service) { [$group,$period]=$this->groupContext($request); $processed=count($service->calculateGroupReportCards(app(\App\Tenancy\TenantContext::class)->id(),$group->id,$period)); return back()->with('success',"$processed bulletin(s) recalculé(s)."); }
    public function validateGroup(Request $request, \App\Services\ReportCardValidationService $service) { [$group,$period]=$this->groupContext($request); $cards=ReportCard::where('tenant_id',app(\App\Tenancy\TenantContext::class)->id())->where('school_group_id',$group->id)->where('period_key',$period)->where('status','draft')->get(); $validated=0; foreach($cards as $card){if($service->validate($card,auth()->id())['ready'])$validated++;} return back()->with('success',"$validated bulletin(s) validé(s)."); }
    public function publishGroup(Request $request, \App\Services\ReportCardValidationService $service) { [$group,$period]=$this->groupContext($request); $cards=ReportCard::where('tenant_id',app(\App\Tenancy\TenantContext::class)->id())->where('school_group_id',$group->id)->where('period_key',$period)->where('status','validated')->get(); foreach($cards as $card)$service->transition($card,'published',auth()->id()); return back()->with('success',$cards->count().' bulletin(s) publié(s).'); }
    private function groupContext(Request $request): array { $data=$request->validate(['group_id'=>'required|integer','period_key'=>['required',Rule::in(array_keys(config('report_cards.periods')))]]); $group=SchoolGroup::whereKey($data['group_id'])->where('is_active',true)->firstOrFail(); return [$group,$data['period_key']]; }
    public function show(ReportCard $reportCard) { abort_unless((int)$reportCard->tenant_id === (int)app(\App\Tenancy\TenantContext::class)->id(),404); return Inertia::render('Admin/ReportCards/Show',['reportCard'=>$reportCard->load(['student','year','level','group','subjects']),'history'=>\App\Models\AuditLog::with('user:id,name')->where('related_type',ReportCard::class)->where('related_id',$reportCard->id)->latest('occurred_at')->get()]); }
}
