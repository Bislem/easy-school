<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SalaryType;
use App\Enums\SalaryCalculationType;
use App\Enums\SalaryItemCategory;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\SalaryConfiguration;
use App\Models\SalaryPayment;
use App\Models\SalaryStatement;
use App\Models\SalaryItem;
use App\Models\Staff;
use App\Models\User;
use App\Models\EmployeeType;
use App\Models\CompanySetting;
use App\Enums\UserRole;
use App\Services\SalaryCalculator;
use App\Services\AlgerianPayrollEngine;
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
use Illuminate\Http\JsonResponse;

class SalariesController extends Controller
{
    public function __construct(private SalaryCalculator $calculator, private AlgerianPayrollEngine $payrollEngine) {}

    public function index(Request $request): Response
    {
        $personnel = User::whereIn('role', [UserRole::TEACHER->value, UserRole::EMPLOYEE->value])
            ->with('staff.employeeType:id,name,is_teacher')->orderBy('name')->get();
        $period = null;
        if (preg_match('/^\d{4}-\d{2}$/', $request->string('period')->toString())) {
            try {
                $period = Carbon::createFromFormat('!Y-m', $request->string('period')->toString());
            } catch (\Throwable) {
                $period = null;
            }
        }

        $statementQuery = SalaryStatement::query()
            ->with(['staff.employeeType:id,name','payments' => fn ($query) => $query->latest('paid_at'),'adjustments','lines','statutoryLines','regulation'])
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('staff', fn ($staff) => $staff
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_code', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('staff_id'), fn($q)=>$q->where('staff_id',$request->integer('staff_id')))
            ->when($request->string('status')->toString(), fn($q,$status)=>$q->where('status',$status))
            ->when($request->string('salary_type')->toString(), fn($q,$type)=>$q->where('salary_type',$type))
            ->when($period, fn($q,$month)=>$q->whereDate('period_start','<=',$month->copy()->endOfMonth())->whereDate('period_end','>=',$month->copy()->startOfMonth()));

        $summaryQuery = clone $statementQuery;
        $summary = [
            'statements' => (clone $summaryQuery)->count(),
            'net' => (float) (clone $summaryQuery)->sum('net_salary'),
            'paid' => (float) (clone $summaryQuery)->sum('amount_paid'),
            'remaining' => (float) (clone $summaryQuery)->sum('remaining_amount'),
            'hours' => (float) (clone $summaryQuery)->get()->sum(fn ($salary) => (float) data_get($salary->calculation_details, 'attendance_worked_hours', 0)),
            'employee_cnas' => (float) (clone $summaryQuery)->sum('employee_cnas'),
            'irg' => (float) (clone $summaryQuery)->sum('irg_amount'),
            'employer_charges' => (float) (clone $summaryQuery)->sum('employer_contributions'),
            'employer_cost' => (float) (clone $summaryQuery)->sum('total_employer_cost'),
            'employees' => (clone $summaryQuery)->distinct('staff_id')->count('staff_id'),
            'missing_legal_information' => $personnel->pluck('staff')->filter(fn($staff)=>!$staff?->social_security_number || !$staff?->nin)->count(),
        ];
        $statements = $statementQuery->latest('period_end')->latest('id')->paginate(15)->withQueryString();
        return Inertia::render('Admin/Salaries/Index', [
            'statements'=>$statements,
            'summary'=>$summary,
            'configurations'=>SalaryConfiguration::with('items')->latest('effective_from')->get(),
            'payments'=>SalaryPayment::with(['staff.employeeType:id,name','statement:id,reference,period_start,period_end,salary_type,base_rate,units,gross_salary,bonuses,deductions,advances,exceptional_payments,reimbursements,net_salary,amount_paid,remaining_amount,status,calculation_details'])->latest('paid_at')->limit(100)->get(),
            'employees'=>$personnel->pluck('staff')->filter()->each->load('salaryItems')->values(),
            'salaryItems'=>SalaryItem::where('active', true)->orderBy('name')->get(),
            'salaryTypes'=>collect(SalaryType::cases())->map(fn($type)=>$type->value),
            'filters'=>$request->only(['search','staff_id','status','period','salary_type']),
            'currency'=>['symbol'=>config('app.currency_symbol'),'code'=>config('app.currency_code')],
        ]);
    }

    public function storeConfiguration(Request $request): RedirectResponse
    {
        $data=$this->validateConfiguration($request);
        DB::transaction(function () use ($data) {
            $items = $data['items']; unset($data['items']);
            $configuration = SalaryConfiguration::create($data);
            $this->syncConfigurationItems($configuration, $items);
        });
        return back()->with('success','Configuration salariale enregistrée.');
    }

    public function configurations(): Response
    {
        return Inertia::render('Admin/Salaries/Configurations', [
            'configurations' => SalaryConfiguration::with('items')->withCount('statements')->latest('effective_from')->get(),
            'salaryItems' => SalaryItem::orderBy('name')->get(),
            'categories' => collect(SalaryItemCategory::cases())->map(fn ($type) => $type->value),
            'calculationTypes' => collect(SalaryCalculationType::cases())->map(fn ($type) => $type->value),
            'currency' => ['symbol' => config('app.currency_symbol'), 'code' => config('app.currency_code')],
        ]);
    }

    public function updateConfiguration(Request $request, SalaryConfiguration $configuration): RedirectResponse
    {
        $data = $this->validateConfiguration($request);
        DB::transaction(function () use ($configuration, $data) {
            $items = $data['items']; unset($data['items']);
            $configuration->update($data);
            $this->syncConfigurationItems($configuration, $items);
        });

        return back()->with('success', 'Configuration salariale mise à jour.');
    }

    public function destroyConfiguration(SalaryConfiguration $configuration): RedirectResponse
    {
        if ($configuration->statements()->exists()) {
            throw ValidationException::withMessages(['configuration' => 'Cette configuration est déjà utilisée par des bulletins et ne peut pas être supprimée. Vous pouvez modifier sa date de fin.']);
        }
        $configuration->delete();

        return back()->with('success', 'Configuration salariale supprimée.');
    }

    private function validateConfiguration(Request $request): array
    {
        $data = $request->validate([
            'name'=>['required','string','max:150'], 'effective_from'=>['required','date'],
            'effective_to'=>['nullable','date','after_or_equal:effective_from'], 'notes'=>['nullable','string','max:5000'],
            'items'=>['required','array','min:1'], 'items.*.salary_item_id'=>['required','distinct','exists:salary_items,id'],
            'items.*.amount_override'=>['nullable','numeric','min:0'], 'items.*.display_order'=>['required','integer','min:0'],
        ]);
        // Legacy columns remain populated so old integrations and reports keep working.
        $first = collect($data['items'])->first();
        $item = SalaryItem::findOrFail($first['salary_item_id']);
        $data['salary_type'] = $item->calculation_type === SalaryCalculationType::HOURLY ? SalaryType::HOURLY->value : SalaryType::MONTHLY->value;
        $data['base_rate'] = $first['amount_override'] ?? $item->default_amount ?? 0;
        return $data;
    }

    private function syncConfigurationItems(SalaryConfiguration $configuration, array $items): void
    {
        $tenantId = $configuration->tenant_id;
        $configuration->items()->sync(collect($items)->mapWithKeys(fn ($item) => [
            $item['salary_item_id'] => ['tenant_id' => $tenantId, 'amount_override' => $item['amount_override'] ?? null, 'display_order' => $item['display_order']],
        ])->all());
    }

    public function storeItem(Request $request): RedirectResponse
    {
        SalaryItem::create($this->validateItem($request));
        return back()->with('success', 'Rubrique de salaire créée.');
    }

    public function updateItem(Request $request, SalaryItem $item): RedirectResponse
    {
        $item->update($this->validateItem($request, $item));
        return back()->with('success', 'Rubrique de salaire mise à jour.');
    }

    public function destroyItem(SalaryItem $item): RedirectResponse
    {
        if ($item->configurations()->exists() || $item->staff()->exists()) {
            throw ValidationException::withMessages(['item' => 'Cette rubrique est utilisée. Désactivez-la pour conserver les données historiques.']);
        }
        $item->delete();
        return back()->with('success', 'Rubrique de salaire supprimée.');
    }

    private function validateItem(Request $request, ?SalaryItem $item = null): array
    {
        return $request->validate([
            'name'=>['required','string','max:150'], 'code'=>['nullable','string','max:50',Rule::unique('salary_items','code')->ignore($item)],
            'category'=>['required',Rule::enum(SalaryItemCategory::class)], 'calculation_type'=>['required',Rule::enum(SalaryCalculationType::class)],
            'default_amount'=>['nullable','numeric','min:0'], 'active'=>['required','boolean'], 'description'=>['nullable','string','max:5000'],
            'subject_to_cnas'=>['required','boolean'], 'subject_to_irg'=>['required','boolean'],
            'salary_item_nature'=>['required',Rule::in(['BASE_SALARY','PRIME','INDEMNITY','OVERTIME','HOURLY_WORK','FAMILY_ALLOWANCE','EXPENSE_REIMBURSEMENT','OTHER_EARNING','DEDUCTION'])],
            'irg_treatment'=>['required',Rule::in(['MONTHLY','OCCASIONAL','EXEMPT'])],
        ]);
    }

    public function storeLegacy(Request $request): RedirectResponse
    {
        $data=$request->validate(['employee_id'=>['required','exists:users,id'],'amount'=>['required','numeric','min:0.01'],'salary_period'=>['required','date_format:Y-m'],'expense_date'=>['required','date'],'payment_method'=>['required',Rule::in(['cash','bank_transfer','cheque','card','other'])],'reference'=>['nullable','string','max:255'],'notes'=>['nullable','string','max:5000']]);
        $user=User::findOrFail($data['employee_id']); abort_unless(in_array($user->role,[UserRole::TEACHER,UserRole::EMPLOYEE],true),422);
        $type=EmployeeType::where('slug',$user->role===UserRole::TEACHER?'teacher':'other')->firstOrFail(); $parts=preg_split('/\s+/',trim($user->name),2);
        $staff=Staff::firstOrCreate(['user_id'=>$user->id],['employee_type_id'=>$type->id,'first_name'=>$parts[0],'last_name'=>$parts[1]??'','email'=>$user->email,'phone'=>$user->phone,'employment_status'=>$user->is_active?'active':'inactive','employee_code'=>'EMP-'.str_pad((string)$user->id,6,'0',STR_PAD_LEFT)]);
        DB::transaction(function()use($data,$staff,$request){ $ref=$data['reference']?:'LEGACY-'.str()->upper(str()->random(8)); $expense=Expense::create(['created_by'=>$request->user()->id,'employee_id'=>$staff->user_id,'staff_id'=>$staff->id,'type'=>'school','category'=>'Salaire','title'=>'Salaire — '.$staff->name.' — '.$data['salary_period'],'amount'=>$data['amount'],'expense_date'=>$data['expense_date'],'salary_period'=>$data['salary_period'].'-01','vendor'=>$staff->name,'payment_method'=>$data['payment_method'],'reference'=>$ref,'notes'=>$data['notes']??null]); $statement=SalaryStatement::create(['staff_id'=>$staff->id,'reference'=>'LEGACY-SAL-'.str()->upper(str()->random(8)),'period_start'=>$data['salary_period'].'-01','period_end'=>$data['salary_period'].'-01','salary_type'=>'custom','base_rate'=>$data['amount'],'units'=>1,'gross_salary'=>$data['amount'],'net_salary'=>$data['amount'],'amount_paid'=>$data['amount'],'remaining_amount'=>0,'status'=>'paid','generated_by'=>$request->user()->id]); $statement->lines()->create(['item_name'=>'Salaire historique','category'=>'BASE_SALARY','calculation_type'=>'FIXED_MONTHLY','quantity'=>1,'rate'=>$data['amount'],'amount'=>$data['amount'],'source'=>'MANUAL','display_order'=>0,'snapshot_data'=>['legacy'=>true]]); SalaryPayment::create(['salary_statement_id'=>$statement->id,'staff_id'=>$staff->id,'expense_id'=>$expense->id,'amount'=>$data['amount'],'paid_at'=>$data['expense_date'],'payment_method'=>$data['payment_method'],'reference'=>'PAY-'.$ref,'notes'=>$data['notes']??null,'created_by'=>$request->user()->id]); });
        return back()->with('success','Salaire historique enregistré et intégré au nouveau grand livre.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $data=$request->validate(['staff_id'=>['required','exists:staff,id'],'salary_configuration_id'=>['nullable','exists:salary_configurations,id'],'period'=>['required','date_format:Y-m'],'items'=>['required','array','min:1'],'items.*.salary_item_id'=>['required','distinct','exists:salary_items,id'],'items.*.amount'=>['required','numeric','min:0'],'items.*.source'=>['required',Rule::in(['CONFIG','DIRECT'])],'notes'=>['nullable','string','max:5000'],'adjustments'=>['array'],'adjustments.*.type'=>['required',Rule::in(['bonus','deduction','advance','exceptional','reimbursement'])],'adjustments.*.label'=>['required','string','max:255'],'adjustments.*.amount'=>['required','numeric','min:0.01'],'adjustments.*.notes'=>['nullable','string','max:2000']]);
        $staff=Staff::findOrFail($data['staff_id']); $start=Carbon::createFromFormat('Y-m',$data['period'])->startOfMonth(); $end=$start->copy()->endOfMonth();
        $configuration=!empty($data['salary_configuration_id'])?SalaryConfiguration::findOrFail($data['salary_configuration_id']):null;
        if($configuration && ($configuration->effective_from->gt($end) || ($configuration->effective_to && $configuration->effective_to->lt($start)))) throw ValidationException::withMessages(['salary_configuration_id'=>'Cette configuration salariale n’est pas active sur la période choisie.']);
        $salaryItems=SalaryItem::whereIn('id',collect($data['items'])->pluck('salary_item_id'))->get()->keyBy('id');
        if ($salaryItems->count() !== count($data['items'])) {
            throw ValidationException::withMessages(['items' => 'Une ou plusieurs rubriques sont indisponibles pour cet établissement.']);
        }
        $selectedItems=collect($data['items'])->map(fn($row,$index)=>['item'=>$salaryItems->get($row['salary_item_id']),'amount'=>(float)$row['amount'],'source'=>$row['source'],'display_order'=>$index]);
        $adjustments=collect($data['adjustments']??[]); $sum=fn(string $type)=>(float)$adjustments->where('type',$type)->sum('amount');
        $bonuses=$sum('bonus'); $deductions=$sum('deduction'); $advances=$sum('advance'); $exceptional=$sum('exceptional'); $reimbursements=$sum('reimbursement');
        $statement=DB::transaction(function()use($staff,$configuration,$start,$end,$bonuses,$deductions,$advances,$exceptional,$reimbursements,$data,$adjustments,$selectedItems,$request){
            $calculation=$this->calculator->calculateItems($staff,$selectedItems,$start,$end);
            $manualLines=$adjustments->map(fn($row)=>['item_name'=>$row['label'],'amount'=>in_array($row['type'],['deduction','advance'],true)?-(float)$row['amount']:(float)$row['amount']])->all();
            $regulation=$this->payrollEngine->regulationFor($end);
            $statutory=$this->payrollEngine->calculate($staff,$end,array_merge($calculation['lines'],$manualLines),$regulation);
            $allDeductions=$deductions+$advances+$calculation['item_deductions'];
            $net=max(0,$calculation['gross']+$bonuses+$exceptional+$reimbursements-$allDeductions-$statutory['employee_cnas']-$statutory['irg_amount']);
            $primary=$selectedItems->first(); $primaryHourly=$primary['item']->calculation_type===SalaryCalculationType::HOURLY;
            $grossTotal=$calculation['gross']+$bonuses+$exceptional+$reimbursements;
            $statement=SalaryStatement::create(['staff_id'=>$staff->id,'salary_configuration_id'=>$configuration?->id,'payroll_regulation_id'=>$regulation->id,'reference'=>'SAL-'.$start->format('Ym').'-'.$staff->employee_code.'-'.str()->upper(str()->random(4)),'period_start'=>$start,'period_end'=>$end,'salary_type'=>$primaryHourly?'hourly':'monthly','base_rate'=>$primary['amount'],'units'=>$primaryHourly?$calculation['details']['attendance_worked_hours']:1,'gross_salary'=>$calculation['gross'],'bonuses'=>$bonuses,'deductions'=>$deductions+$calculation['item_deductions'],'advances'=>$advances,'exceptional_payments'=>$exceptional,'reimbursements'=>$reimbursements,'cnas_base'=>$statutory['cnas_base'],'employee_cnas'=>$statutory['employee_cnas'],'irg_base'=>$statutory['irg_base'],'irg_amount'=>$statutory['irg_amount'],'employer_contributions'=>$statutory['employer_contributions'],'total_employer_cost'=>$grossTotal+$statutory['employer_contributions'],'statutory_calculation'=>$statutory['breakdown'],'legal_warnings'=>$statutory['warnings'],'net_salary'=>$net,'amount_paid'=>0,'remaining_amount'=>$net,'status'=>$net>0?'pending':'paid','calculation_details'=>$calculation['details'],'notes'=>$data['notes']??null,'generated_by'=>$request->user()->id]);
            $statement->teacherAttendances()->attach($calculation['details']['teacher_attendance_ids']);
            $statement->employeeAttendances()->attach($calculation['details']['employee_attendance_ids']);
            foreach($calculation['lines'] as $line)$statement->lines()->create($line);
            foreach($statutory['lines'] as $line)$statement->statutoryLines()->create($line);
            foreach($adjustments as $index=>$adjustment){
                $statement->adjustments()->create($adjustment);
                $isDeduction=in_array($adjustment['type'],['deduction','advance'],true);
                $statement->lines()->create(['item_name'=>$adjustment['label'],'category'=>$isDeduction?'DEDUCTION':'PRIME','calculation_type'=>'FIXED_MONTHLY','quantity'=>1,'rate'=>$adjustment['amount'],'amount'=>$isDeduction?-$adjustment['amount']:$adjustment['amount'],'source'=>'MANUAL','display_order'=>100+$index,'snapshot_data'=>['adjustment_type'=>$adjustment['type'],'notes'=>$adjustment['notes']??null]]);
            }
            $staff->salaryItems()->sync(collect($data['items'])->mapWithKeys(fn($row,$index)=>[$row['salary_item_id']=>['tenant_id'=>$staff->tenant_id,'salary_configuration_id'=>$configuration?->id,'amount_override'=>$row['amount'],'source'=>$row['source'],'display_order'=>$index]])->all());
            return $statement;
        });
        return back()->with('success','Bulletin '.$statement->reference.' calculé.');
    }

    public function attendancePreview(Request $request): JsonResponse
    {
        $data = $request->validate(['staff_id'=>['required','exists:staff,id'],'period'=>['required','date_format:Y-m']]);
        $staff = Staff::findOrFail($data['staff_id']);
        $start = Carbon::createFromFormat('Y-m', $data['period'])->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $available = $this->calculator->attendanceSnapshot($staff, $start, $end);
        $monthlyTotal = $this->calculator->attendanceSnapshot($staff, $start, $end, false);

        return response()->json([
            'monthly_worked_hours' => $monthlyTotal['worked_hours'],
            'available_worked_hours' => $available['worked_hours'],
            'already_accounted_hours' => round($monthlyTotal['worked_hours'] - $available['worked_hours'], 2),
        ]);
    }

    public function destroy(SalaryStatement $statement): RedirectResponse
    {
        if ($statement->payments()->exists() || (float) $statement->amount_paid > 0) {
            throw ValidationException::withMessages(['statement' => 'Un bulletin payé, même partiellement, ne peut pas être supprimé.']);
        }
        $reference = $statement->reference;
        $statement->delete();

        return back()->with('success', "Calcul {$reference} supprimé. Ses pointages sont à nouveau disponibles.");
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
        $statement->load(['staff.employeeType','payments','adjustments','lines','statutoryLines','regulation','configuration']);
        return Pdf::loadView('admin.salaries.statement',['statement'=>$statement,'school'=>CompanySetting::current(),'currency'=>config('app.currency_symbol')])->download($statement->reference.'.pdf');
    }

    public function paymentReceipt(SalaryPayment $payment): HttpResponse
    {
        $payment->load(['staff.employeeType', 'statement.configuration', 'statement.adjustments', 'creator:id,name']);

        return Pdf::loadView('admin.salaries.payment-receipt', [
            'payment' => $payment,
            'school' => CompanySetting::current(),
            'currency' => config('app.currency_symbol'),
        ])->download('recu-salaire-'.$payment->paid_at->format('Ymd').'-'.$payment->id.'.pdf');
    }
}
