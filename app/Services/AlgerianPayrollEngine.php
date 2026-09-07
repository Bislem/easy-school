<?php

namespace App\Services;

use App\Models\PayrollRegulation;
use App\Models\Staff;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AlgerianPayrollEngine
{
    public function regulationFor(CarbonInterface $period): PayrollRegulation
    {
        return PayrollRegulation::where('country','DZ')->where('active',true)->whereDate('valid_from','<=',$period)
            ->where(fn($q)=>$q->whereNull('valid_until')->orWhereDate('valid_until','>=',$period))->latest('valid_from')->first()
            ?? throw ValidationException::withMessages(['regulation'=>'Aucune réglementation de paie algérienne active pour cette période.']);
    }

    public function calculate(Staff $employee, CarbonInterface $period, iterable $salaryLines, PayrollRegulation $regulation): array
    {
        $lines=collect($salaryLines); $rules=$regulation->rules; $cnas=$rules['cnas']; $irg=$rules['irg'];
        $cnasBase=$this->eligibleBase($lines,'subject_to_cnas');
        $employeeCnas=round($cnasBase*(float)$cnas['employee_rate'],2);
        $employerCnas=round($cnasBase*(float)$cnas['employer_rate'],2);
        $socialWorks=round($cnasBase*(float)($cnas['social_works_rate']??0),2);
        $monthlyIrgGross=$this->eligibleBase($lines,'subject_to_irg',fn($line)=>($line['irg_treatment']??'MONTHLY')==='MONTHLY');
        $irgBase=max(0,round($monthlyIrgGross-$employeeCnas,2));
        $monthlyIrg=$this->monthlyIrg($irgBase,$irg);
        $occasionalBase=$this->eligibleBase($lines,'subject_to_irg',fn($line)=>($line['irg_treatment']??'MONTHLY')==='OCCASIONAL');
        $occasionalIrg=round($occasionalBase*(float)($irg['occasional_rate']??0.10),2);
        $irgAmount=round($monthlyIrg+$occasionalIrg,2); $employerCharges=$employerCnas+$socialWorks;
        $warnings=$this->legalWarnings($employee,$lines);
        return ['regulation_id'=>$regulation->id,'regulation_version'=>$regulation->version,'cnas_base'=>$cnasBase,'employee_cnas'=>$employeeCnas,'irg_base'=>$irgBase,'irg_amount'=>$irgAmount,'employer_cnas'=>$employerCnas,'social_works'=>$socialWorks,'employer_contributions'=>$employerCharges,'warnings'=>$warnings,
            'lines'=>[
                ['code'=>'CNAS_EMPLOYEE','label'=>'CNAS salarié','base'=>$cnasBase,'rate'=>$cnas['employee_rate'],'amount'=>$employeeCnas,'affects_net'=>true,'employer_charge'=>false,'details'=>['formula'=>'assiette_cnas × taux_salarie']],
                ['code'=>'IRG','label'=>'IRG traitements et salaires','base'=>$irgBase,'rate'=>null,'amount'=>$irgAmount,'affects_net'=>true,'employer_charge'=>false,'details'=>['monthly_irg'=>$monthlyIrg,'occasional_base'=>$occasionalBase,'occasional_irg'=>$occasionalIrg,'brackets'=>$irg['annual_brackets']]],
                ['code'=>'CNAS_EMPLOYER','label'=>'CNAS employeur','base'=>$cnasBase,'rate'=>$cnas['employer_rate'],'amount'=>$employerCnas,'affects_net'=>false,'employer_charge'=>true,'details'=>['formula'=>'assiette_cnas × taux_employeur']],
                ['code'=>'SOCIAL_WORKS','label'=>'Œuvres sociales','base'=>$cnasBase,'rate'=>$cnas['social_works_rate']??0,'amount'=>$socialWorks,'affects_net'=>false,'employer_charge'=>true,'details'=>[]],
            ],
            'breakdown'=>['gross_cnas_eligible'=>$cnasBase,'employee_cnas_deduction'=>$employeeCnas,'gross_irg_eligible'=>$monthlyIrgGross,'taxable_after_cnas'=>$irgBase,'monthly_irg'=>$monthlyIrg,'occasional_irg'=>$occasionalIrg,'regulation'=>['id'=>$regulation->id,'name'=>$regulation->name,'version'=>$regulation->version,'rules'=>$rules,'sources'=>$regulation->source_references]],
        ];
    }

    private function eligibleBase(Collection $lines,string $flag,?callable $filter=null):float
    { return round((float)$lines->filter(fn($line)=>(bool)($line[$flag]??false)&&($line['amount']??0)>0&&(!$filter||$filter($line)))->sum('amount'),2); }

    public function monthlyIrg(float $taxable,array $rules):float
    {
        if($taxable<=(float)$rules['exemption_monthly'])return 0.0;
        $annual=$taxable*12; $tax=0.0; $lower=0.0;
        foreach($rules['annual_brackets'] as $bracket){$upper=$bracket['up_to'];$slice=$upper===null?max(0,$annual-$lower):max(0,min($annual,(float)$upper)-$lower);$tax+=$slice*(float)$bracket['rate'];if($upper===null||$annual<=(float)$upper)break;$lower=(float)$upper;}
        $monthly=$tax/12; $abatement=min((float)$rules['abatement_max_monthly'],max((float)$rules['abatement_min_monthly'],$monthly*(float)$rules['abatement_rate']));
        $after=max(0,$monthly-$abatement);
        if($taxable<=(float)$rules['low_income_upper'])$after=max(0,$after*(float)$rules['low_income_multiplier']-(float)$rules['low_income_offset']);
        return round($after,2);
    }

    public function legalWarnings(Staff $employee,Collection $lines):array
    {
        $warnings=[]; if(!$employee->employee_code)$warnings[]='Matricule employé manquant'; if(!$employee->social_security_number)$warnings[]='Numéro CNAS manquant'; if(!$employee->nin)$warnings[]='NIN manquant'; if(!$employee->birth_date)$warnings[]='Date de naissance manquante'; if(!$employee->employee_type_id)$warnings[]='Informations d’emploi manquantes';
        foreach($lines as $line){if(!array_key_exists('subject_to_cnas',$line)||$line['subject_to_cnas']===null)$warnings[]='Rubrique sans classification CNAS : '.$line['item_name'];if(!array_key_exists('subject_to_irg',$line)||$line['subject_to_irg']===null)$warnings[]='Rubrique sans classification IRG : '.$line['item_name'];}
        return array_values(array_unique($warnings));
    }
}
