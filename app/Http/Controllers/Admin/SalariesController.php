<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SalaryType;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\SalaryConfiguration;
use App\Models\SalaryPayment;
use App\Models\SalaryStatement;
use App\Models\Staff;
use App\Models\User;
use App\Models\EmployeeType;
use App\Enums\UserRole;
use App\Services\SalaryCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SalariesController extends Controller
{
    public function __construct(private SalaryCalculator $calculator) {}

    public function index(Request $request): Response
    {
        $personnel = User::whereIn('role', [UserRole::TEACHER->value, UserRole::EMPLOYEE->value])
            ->with('staff.employeeType:id,name,is_teacher')->orderBy('name')->get();
        $statements = SalaryStatement::with(['staff.employeeType:id,name','payments','adjustments'])
            ->when($request->filled('staff_id'), fn($q)=>$q->where('staff_id',$request->integer('staff_id')))
            ->when($request->string('status')->toString(), fn($q,$status)=>$q->where('status',$status))
            ->when($request->string('period')->toString(), fn($q,$period)=>$q->whereDate('period_start','<=',$period.'-31')->whereDate('period_end','>=',$period.'-01'))
            ->latest('period_end')->paginate(15)->withQueryString();
        return Inertia::render('Admin/Salaries/Index', [
            'statements'=>$statements,
            'configurations'=>SalaryConfiguration::latest('effective_from')->get(),
            'payments'=>SalaryPayment::with(['staff.employeeType:id,name','statement:id,reference,period_start,period_end'])->latest('paid_at')->limit(100)->get(),
            'employees'=>$personnel->pluck('staff')->filter()->values(),
            'salaryTypes'=>collect(SalaryType::cases())->map(fn($type)=>$type->value),
            'filters'=>$request->only(['staff_id','status','period']),
            'currency'=>['symbol'=>config('app.currency_symbol'),'code'=>config('app.currency_code')],
        ]);
    }

    public function storeConfiguration(Request $request): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:150'],'salary_type'=>['required',Rule::enum(SalaryType::class)],'base_rate'=>['required','numeric','min:0'],'effective_from'=>['required','date'],'effective_to'=>['nullable','date','after_or_equal:effective_from'],'notes'=>['nullable','string','max:5000']]);
        SalaryConfiguration::create($data);
        return back()->with('success','Configuration salariale enregistrée.');
    }

    public function storeLegacy(Request $request): RedirectResponse
    {
        $data=$request->validate(['employee_id'=>['required','exists:users,id'],'amount'=>['required','numeric','min:0.01'],'salary_period'=>['required','date_format:Y-m'],'expense_date'=>['required','date'],'payment_method'=>['required',Rule::in(['cash','bank_transfer','cheque','card','other'])],'reference'=>['nullable','string','max:255'],'notes'=>['nullable','string','max:5000']]);
        $user=User::findOrFail($data['employee_id']); abort_unless(in_array($user->role,[UserRole::TEACHER,UserRole::EMPLOYEE],true),422);
        $type=EmployeeType::where('slug',$user->role===UserRole::TEACHER?'teacher':'other')->firstOrFail(); $parts=preg_split('/\s+/',trim($user->name),2);
        $staff=Staff::firstOrCreate(['user_id'=>$user->id],['employee_type_id'=>$type->id,'first_name'=>$parts[0],'last_name'=>$parts[1]??'','email'=>$user->email,'phone'=>$user->phone,'employment_status'=>$user->is_active?'active':'inactive','employee_code'=>'EMP-'.str_pad((string)$user->id,6,'0',STR_PAD_LEFT)]);
        DB::transaction(function()use($data,$staff,$request){ $ref=$data['reference']?:'LEGACY-'.str()->upper(str()->random(8)); $expense=Expense::create(['created_by'=>$request->user()->id,'employee_id'=>$staff->user_id,'staff_id'=>$staff->id,'type'=>'school','category'=>'Salaire','title'=>'Salaire — '.$staff->name.' — '.$data['salary_period'],'amount'=>$data['amount'],'expense_date'=>$data['expense_date'],'salary_period'=>$data['salary_period'].'-01','vendor'=>$staff->name,'payment_method'=>$data['payment_method'],'reference'=>$ref,'notes'=>$data['notes']??null]); $statement=SalaryStatement::create(['staff_id'=>$staff->id,'reference'=>'LEGACY-SAL-'.str()->upper(str()->random(8)),'period_start'=>$data['salary_period'].'-01','period_end'=>$data['salary_period'].'-01','salary_type'=>'custom','base_rate'=>$data['amount'],'units'=>1,'gross_salary'=>$data['amount'],'net_salary'=>$data['amount'],'amount_paid'=>$data['amount'],'remaining_amount'=>0,'status'=>'paid','generated_by'=>$request->user()->id]); SalaryPayment::create(['salary_statement_id'=>$statement->id,'staff_id'=>$staff->id,'expense_id'=>$expense->id,'amount'=>$data['amount'],'paid_at'=>$data['expense_date'],'payment_method'=>$data['payment_method'],'reference'=>'PAY-'.$ref,'notes'=>$data['notes']??null,'created_by'=>$request->user()->id]); });
        return back()->with('success','Salaire historique enregistré et intégré au nouveau grand livre.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $data=$request->validate(['staff_id'=>['required','exists:staff,id'],'salary_configuration_id'=>['required','exists:salary_configurations,id'],'period'=>['required','date_format:Y-m'],'worked_units'=>['nullable','numeric','min:0'],'manual_amount'=>['nullable','numeric','min:0'],'notes'=>['nullable','string','max:5000'],'adjustments'=>['array'],'adjustments.*.type'=>['required',Rule::in(['bonus','deduction','advance','exceptional','reimbursement'])],'adjustments.*.label'=>['required','string','max:255'],'adjustments.*.amount'=>['required','numeric','min:0.01'],'adjustments.*.notes'=>['nullable','string','max:2000']]);
        $staff=Staff::findOrFail($data['staff_id']); $start=Carbon::createFromFormat('Y-m',$data['period'])->startOfMonth(); $end=$start->copy()->endOfMonth();
        $configuration=SalaryConfiguration::findOrFail($data['salary_configuration_id']);
        if($configuration->effective_from->gt($end) || ($configuration->effective_to && $configuration->effective_to->lt($start))) throw ValidationException::withMessages(['salary_configuration_id'=>'Cette configuration salariale n’est pas active sur la période choisie.']);
        $adjustments=collect($data['adjustments']??[]); $sum=fn(string $type)=>(float)$adjustments->where('type',$type)->sum('amount');
        $bonuses=$sum('bonus'); $deductions=$sum('deduction'); $advances=$sum('advance'); $exceptional=$sum('exceptional'); $reimbursements=$sum('reimbursement');
        $statement=DB::transaction(function()use($staff,$configuration,$start,$end,$bonuses,$deductions,$advances,$exceptional,$reimbursements,$data,$adjustments,$request){
            $calculation=$this->calculator->calculate($staff,$configuration,$start,$end,isset($data['worked_units'])?(float)$data['worked_units']:null,isset($data['manual_amount'])?(float)$data['manual_amount']:null);
            $net=max(0,$calculation['gross']+$bonuses+$exceptional+$reimbursements-$deductions-$advances);
            $statement=SalaryStatement::create(['staff_id'=>$staff->id,'salary_configuration_id'=>$configuration->id,'reference'=>'SAL-'.$start->format('Ym').'-'.$staff->employee_code.'-'.str()->upper(str()->random(4)),'period_start'=>$start,'period_end'=>$end,'salary_type'=>$configuration->salary_type,'base_rate'=>$configuration->base_rate,'units'=>$calculation['units'],'gross_salary'=>$calculation['gross'],'bonuses'=>$bonuses,'deductions'=>$deductions,'advances'=>$advances,'exceptional_payments'=>$exceptional,'reimbursements'=>$reimbursements,'net_salary'=>$net,'amount_paid'=>0,'remaining_amount'=>$net,'status'=>$net>0?'pending':'paid','calculation_details'=>$calculation['details'],'notes'=>$data['notes']??null,'generated_by'=>$request->user()->id]);
            $statement->teacherAttendances()->attach($calculation['details']['teacher_attendance_ids']);
            foreach($adjustments as $adjustment)$statement->adjustments()->create($adjustment);
            return $statement;
        });
        return back()->with('success','Bulletin '.$statement->reference.' calculé.');
    }

    public function pay(Request $request, SalaryStatement $statement): RedirectResponse
    {
        $data=$request->validate(['amount'=>['required','numeric','min:0.01'],'paid_at'=>['required','date'],'payment_method'=>['required',Rule::in(['cash','bank_transfer','cheque','card','other'])],'reference'=>['nullable','string','max:255','unique:salary_payments,reference'],'notes'=>['nullable','string','max:3000']]);
        DB::transaction(function()use($statement,$data,$request){
            $statement=SalaryStatement::lockForUpdate()->findOrFail($statement->id); $amount=(float)$data['amount'];
            if($amount>(float)$statement->remaining_amount) throw ValidationException::withMessages(['amount'=>'Le paiement dépasse le montant restant.']);
            $paymentRef=$data['reference']?:'PAY-'.$statement->reference.'-'.str()->upper(str()->random(5));
            $expense=Expense::create(['created_by'=>$request->user()->id,'employee_id'=>$statement->staff->user_id,'staff_id'=>$statement->staff_id,'type'=>'school','category'=>'Salaire','title'=>'Paiement '.$statement->reference,'amount'=>$amount,'expense_date'=>$data['paid_at'],'salary_period'=>$statement->period_start,'vendor'=>$statement->staff->name,'payment_method'=>$data['payment_method'],'reference'=>$paymentRef,'notes'=>$data['notes']??null]);
            SalaryPayment::create(['salary_statement_id'=>$statement->id,'staff_id'=>$statement->staff_id,'expense_id'=>$expense->id,'amount'=>$amount,'paid_at'=>$data['paid_at'],'payment_method'=>$data['payment_method'],'reference'=>$paymentRef,'notes'=>$data['notes']??null,'created_by'=>$request->user()->id]);
            $paid=(float)$statement->amount_paid+$amount; $remaining=max(0,(float)$statement->net_salary-$paid);
            $statement->update(['amount_paid'=>$paid,'remaining_amount'=>$remaining,'status'=>$remaining<=0?'paid':'partially_paid']);
        });
        return back()->with('success','Paiement enregistré. L’historique est définitif.');
    }

    public function print(SalaryStatement $statement): HttpResponse
    {
        $statement->load(['staff.employeeType','payments','adjustments','configuration','teacherAttendances.scheduledTeacher:id,name','teacherAttendances.actualTeacher:id,name','teacherAttendances.session.group.plan.course','teacherAttendances.session.group.plan.level']);
        return Pdf::loadView('admin.salaries.statement',['statement'=>$statement,'currency'=>config('app.currency_symbol')])->download($statement->reference.'.pdf');
    }
}
