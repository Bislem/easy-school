<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\SalaryPayment;
use App\Models\SalaryStatement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SalaryPortalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $staff=$this->staff($request);
        $filters=$request->validate(['period'=>['nullable','date_format:Y-m']]);
        $period=!empty($filters['period'])?Carbon::createFromFormat('!Y-m',$filters['period']):null;
        $query=SalaryStatement::where('staff_id',$staff->id)->with(['configuration:id,name,salary_type,base_rate','payments','adjustments','teacherAttendances.session.group.plan.level.course','employeeAttendances'])->when($period,fn($query,$month)=>$query->whereDate('period_start','<=',$month->copy()->endOfMonth())->whereDate('period_end','>=',$month->copy()->startOfMonth()));
        $summaryQuery=clone $query;
        return Inertia::render('Salary/Index',['employee'=>$staff->load('employeeType'),'statements'=>$query->latest('period_end')->paginate(12)->withQueryString(),'summary'=>['gross'=>(float)(clone $summaryQuery)->sum('gross_salary'),'net'=>(float)(clone $summaryQuery)->sum('net_salary'),'paid'=>(float)(clone $summaryQuery)->sum('amount_paid'),'remaining'=>(float)(clone $summaryQuery)->sum('remaining_amount'),'hours'=>(float)(clone $summaryQuery)->get()->sum(fn($statement)=>(float)data_get($statement->calculation_details,'attendance_worked_hours',0))],'availableMonths'=>SalaryStatement::where('staff_id',$staff->id)->get(['period_start'])->map(fn($statement)=>$statement->period_start->format('Y-m'))->unique()->sortDesc()->values(),'filters'=>$filters,'currency'=>config('app.currency_symbol')]);
    }

    public function statement(Request $request,SalaryStatement $statement):HttpResponse{$staff=$this->staff($request);abort_unless($statement->staff_id===$staff->id,403);$statement->load(['staff.employeeType','payments','adjustments','configuration','teacherAttendances.scheduledTeacher:id,name','teacherAttendances.actualTeacher:id,name','teacherAttendances.session.group.plan.level.course','employeeAttendances']);return Pdf::loadView('admin.salaries.statement',['statement'=>$statement,'currency'=>config('app.currency_symbol')])->download($statement->reference.'.pdf');}
    public function receipt(Request $request,SalaryPayment $payment):HttpResponse{$staff=$this->staff($request);abort_unless($payment->staff_id===$staff->id,403);$payment->load(['staff.employeeType','statement.configuration','statement.adjustments','creator:id,name']);return Pdf::loadView('admin.salaries.payment-receipt',['payment'=>$payment,'school'=>CompanySetting::current(),'currency'=>config('app.currency_symbol')])->download('recu-salaire-'.$payment->paid_at->format('Ymd').'-'.$payment->id.'.pdf');}
    private function staff(Request $request){$staff=$request->user()->staff;abort_unless($staff,403,'Aucun profil employé lié.');return $staff;}
}
