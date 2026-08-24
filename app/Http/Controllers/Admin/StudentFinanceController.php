<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\CourseEnrollment;
use App\Models\StudentPayment;
use App\Services\EnrollmentFinanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class StudentFinanceController extends Controller
{
    public function __construct(private EnrollmentFinanceService $finance) {}

    public function index(Request $request): Response
    {
        CourseEnrollment::whereNotNull('student_id')->with(['form.course', 'trainingPlanGroup.plan.course', 'installments', 'payments'])->each(fn($enrollment) => $this->finance->refresh($enrollment));
        $enrollments = CourseEnrollment::with(['student:id,first_name,last_name', 'form.course:id,title,price', 'trainingPlanGroup.plan.course', 'installments', 'payments:id,course_enrollment_id,status,payment_date'])
            ->whereNotNull('student_id')->when($request->filled('status'), fn($q)=>$q->where('payment_status', $request->string('status')))
            ->when($request->filled('search'), function($q) use ($request) { $search=$request->string('search'); $q->whereHas('student', fn($s)=>$s->where('first_name','like',"%{$search}%")->orWhere('last_name','like',"%{$search}%")); })
            ->latest('registered_at')->paginate(20)->withQueryString();
        $payments = StudentPayment::with(['student:id,first_name,last_name', 'enrollment.form.course:id,title', 'enrollment.trainingPlanGroup.plan.course', 'recorder:id,name'])->latest('payment_date')->limit(100)->get();
        $expected=(float) CourseEnrollment::whereNotNull('student_id')->sum('final_price'); $collected=(float) CourseEnrollment::whereNotNull('student_id')->sum('total_paid');
        return Inertia::render('Admin/Finance/Index', ['enrollments'=>$enrollments, 'payments'=>$payments, 'methods'=>collect(PaymentMethod::cases())->map(fn($m)=>['value'=>$m->value,'label'=>$m->label()]), 'filters'=>$request->only(['search','status']), 'stats'=>['expected'=>$expected,'collected'=>$collected,'remaining'=>max(0,$expected-$collected),'overdue'=>(float)CourseEnrollment::where('payment_status','overdue')->sum('remaining_balance')], 'currency'=>['symbol'=>config('app.currency_symbol'),'code'=>config('app.currency_code')]]);
    }

    public function configure(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->student_id, 422);
        $data=$request->validate(['formation_price'=>['required','numeric','min:0'],'discount_amount'=>['nullable','numeric','min:0'],'installments'=>['array'],'installments.*.amount'=>['required','numeric','min:0.01'],'installments.*.due_date'=>['required','date'],'installments.*.notes'=>['nullable','string','max:1000']]);
        DB::transaction(function() use ($enrollment,$data,$request) {
            $newDiscount=(float)($data['discount_amount']??0); $changes=[];
            if((float)$enrollment->formation_price!==(float)$data['formation_price'])$changes[]='Prix formation: '.$enrollment->formation_price.' → '.$data['formation_price'];
            if((float)$enrollment->discount_amount!==$newDiscount)$changes[]='Remise: '.$enrollment->discount_amount.' → '.$newDiscount;
            $enrollment->update(['formation_price'=>$data['formation_price'],'discount_amount'=>$newDiscount]);
            if($changes)$enrollment->financialAdjustments()->create(['type'=>'configuration','amount'=>0,'reason'=>implode('; ',$changes),'created_by'=>$request->user()->id]);
            foreach($data['installments']??[] as $item)$enrollment->installments()->create($item);
            $this->finance->refresh($enrollment);
        });
        return back()->with('success','Conditions financières enregistrées.');
    }

    public function adjustment(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        $data=$request->validate(['type'=>['required',Rule::in(['charge','credit'])],'amount'=>['required','numeric','min:0.01'],'reason'=>['required','string','max:1000']]);
        DB::transaction(function() use ($enrollment,$data,$request) { $enrollment->financialAdjustments()->create([...$data,'created_by'=>$request->user()->id]); $signed=$data['type']==='charge'?(float)$data['amount']:-((float)$data['amount']); $enrollment->increment('adjustment_total',$signed); $this->finance->refresh($enrollment); });
        return back()->with('success','Ajustement audité enregistré.');
    }

    public function pay(Request $request, CourseEnrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->student_id,422);
        $data=$request->validate(['amount'=>['required','numeric','min:0.01'],'payment_date'=>['required','date'],'payment_method'=>['required',Rule::enum(PaymentMethod::class)],'student_installment_id'=>['nullable',Rule::exists('student_installments','id')->where('course_enrollment_id',$enrollment->id)],'reference'=>['nullable','string','max:240','unique:student_payments,reference'],'notes'=>['nullable','string','max:2000']]);
        DB::transaction(function() use ($enrollment,$data,$request) { $locked=CourseEnrollment::lockForUpdate()->findOrFail($enrollment->id); $this->finance->refresh($locked); $locked->refresh(); if((float)$data['amount']>(float)$locked->remaining_balance) throw ValidationException::withMessages(['amount'=>'Le montant dépasse le solde restant.']); $previous=(float)$locked->remaining_balance; StudentPayment::create([...$data,'reference'=>$data['reference']??'REC-'.now()->format('Ymd').'-'.str()->upper(str()->random(8)),'student_id'=>$locked->student_id,'course_enrollment_id'=>$locked->id,'status'=>'completed','recorded_by'=>$request->user()->id,'previous_balance'=>$previous,'remaining_balance'=>max(0,$previous-(float)$data['amount'])]); $this->finance->refresh($locked); });
        return back()->with('success','Paiement enregistré.');
    }

    public function reverse(Request $request, StudentPayment $payment): RedirectResponse
    {
        abort_unless($payment->status==='completed' && !StudentPayment::where('reverses_payment_id',$payment->id)->exists(),422);
        $data=$request->validate(['reason'=>['required','string','max:2000']]);
        DB::transaction(function() use ($payment,$data,$request) { $enrollment=CourseEnrollment::lockForUpdate()->findOrFail($payment->course_enrollment_id); $this->finance->refresh($enrollment); $previous=(float)$enrollment->fresh()->remaining_balance; StudentPayment::create(['reference'=>'ANN-'.$payment->reference,'student_id'=>$payment->student_id,'course_enrollment_id'=>$payment->course_enrollment_id,'student_installment_id'=>$payment->student_installment_id,'amount'=>-((float)$payment->amount),'payment_date'=>today(),'payment_method'=>$payment->payment_method,'status'=>'reversal','recorded_by'=>$request->user()->id,'reverses_payment_id'=>$payment->id,'previous_balance'=>$previous,'remaining_balance'=>$previous+(float)$payment->amount,'notes'=>$data['reason']]); $this->finance->refresh($enrollment); });
        return back()->with('success','Paiement contrepassé; les deux écritures sont conservées.');
    }

    public function receipt(StudentPayment $payment): HttpResponse
    {
        $payment->load(['student','enrollment.form.course','enrollment.trainingPlanGroup.plan.course','recorder']);
        return Pdf::loadView('admin.finance.receipt',['payment'=>$payment,'school'=>CompanySetting::current(),'currency'=>config('app.currency_symbol')])->download($payment->reference.'.pdf');
    }
}
