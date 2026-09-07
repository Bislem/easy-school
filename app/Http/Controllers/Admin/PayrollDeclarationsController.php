<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\PayrollDeclaration;
use App\Models\PayrollRegulation;
use App\Models\SalaryStatement;
use App\Services\CnasDasExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PayrollDeclarationsController extends Controller
{
    public function index(Request $request, CnasDasExporter $dasExporter): Response
    {
        $year=(int)($request->integer('year')?:now()->year); $start=Carbon::create($year)->startOfYear(); $end=$start->copy()->endOfYear();
        $statements=SalaryStatement::with(['staff.employeeType','regulation'])->whereBetween('period_end',[$start,$end])->get();
        $school=CompanySetting::current(); $errors=[]; $warnings=[];
        if(!$school->cnas_employer_number)$errors[]='Numéro employeur CNAS manquant dans les paramètres de l’établissement.';
        if(!$school->legal_name)$errors[]='Raison sociale manquante.'; if(!$school->address_line_1)$errors[]='Adresse employeur manquante.';
        foreach($statements->groupBy('staff_id') as $employeeStatements){$staff=$employeeStatements->first()->staff;if(!$staff->social_security_number)$errors[]="{$staff->name} : numéro CNAS manquant";if(!$staff->birth_date)$errors[]="{$staff->name} : date de naissance manquante";if(!$staff->nin)$warnings[]="{$staff->name} : NIN manquant";}
        $preview=['year'=>$year,'employee_count'=>$statements->pluck('staff_id')->unique()->count(),'declared_amount'=>(float)$statements->sum('cnas_base'),'employee_cnas'=>(float)$statements->sum('employee_cnas'),'employer_charges'=>(float)$statements->sum('employer_contributions'),'irg'=>(float)$statements->sum('irg_amount'),'errors'=>array_values(array_unique($errors)),'warnings'=>array_values(array_unique($warnings))];
        return Inertia::render('Admin/Salaries/Declarations',['preview'=>$preview,'declarations'=>PayrollDeclaration::latest()->get(),'dasExportSupported'=>$dasExporter->supported(),'portals'=>['cnas'=>$school->cnas_portal_url?:config('payroll.portals.cnas'),'dgi'=>$school->dgi_portal_url?:config('payroll.portals.dgi')],'filters'=>['year'=>$year]]);
    }
    public function store(Request $request):RedirectResponse
    {
        $data=$request->validate(['declaration_type'=>['required','in:CNAS_DAS,CNAS_DAC,DGI_G29,DGI_G50'],'period'=>['required','regex:/^\d{4}$/'],'employee_count'=>['required','integer','min:0'],'declared_amount'=>['required','numeric','min:0'],'errors'=>['array'],'warnings'=>['array']]);
        $regulation=PayrollRegulation::where('country','DZ')->where('active',true)->whereDate('valid_from','<=',$data['period'].'-12-31')->latest('valid_from')->first();
        PayrollDeclaration::updateOrCreate(['declaration_type'=>$data['declaration_type'],'period'=>$data['period']],['payroll_regulation_id'=>$regulation?->id,'employee_count'=>$data['employee_count'],'declared_amount'=>$data['declared_amount'],'status'=>empty($data['errors'])?'VALIDATED':'DRAFT','validation_result'=>['errors'=>$data['errors']??[],'warnings'=>$data['warnings']??[]],'generated_by'=>$request->user()->id]);
        return back()->with('success','Aperçu de déclaration enregistré.');
    }
    public function declared(PayrollDeclaration $declaration):RedirectResponse{$declaration->update(['status'=>'DECLARED','declared_at'=>now()]);return back()->with('success','Déclaration marquée comme déposée.');}
}
