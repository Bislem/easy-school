<?php

use App\Models\PayrollRegulation;
use App\Models\Staff;
use App\Services\AlgerianPayrollEngine;
use App\Services\CnasDasExporter;

function dzRegulation(): PayrollRegulation
{
    return new PayrollRegulation(['version'=>'TEST-2026','rules'=>['cnas'=>['employee_rate'=>.09,'employer_rate'=>.25,'social_works_rate'=>.005],'irg'=>['annual_brackets'=>[['up_to'=>240000,'rate'=>0],['up_to'=>480000,'rate'=>.23],['up_to'=>960000,'rate'=>.27],['up_to'=>1920000,'rate'=>.30],['up_to'=>3840000,'rate'=>.33],['up_to'=>null,'rate'=>.35]],'abatement_rate'=>.40,'abatement_min_monthly'=>1000,'abatement_max_monthly'=>1500,'exemption_monthly'=>30000,'low_income_upper'=>35000,'low_income_multiplier'=>137/51,'low_income_offset'=>27925/8,'occasional_rate'=>.10]]]);
}

test('cnas bases and employee and employer charges stay separated', function () {
    $result=app(AlgerianPayrollEngine::class)->calculate(new Staff(['employee_code'=>'E1','social_security_number'=>'123','nin'=>'456','birth_date'=>'1990-01-01','employee_type_id'=>1]),now(),[
        ['item_name'=>'Base','amount'=>50000,'subject_to_cnas'=>true,'subject_to_irg'=>true,'irg_treatment'=>'MONTHLY'],
        ['item_name'=>'Frais','amount'=>5000,'subject_to_cnas'=>false,'subject_to_irg'=>false,'irg_treatment'=>'EXEMPT'],
    ],dzRegulation());
    expect($result['cnas_base'])->toBe(50000.0)->and($result['employee_cnas'])->toBe(4500.0)->and($result['employer_cnas'])->toBe(12500.0)->and($result['employer_contributions'])->toBe(12750.0);
});

test('irg progressive engine applies exemption and configured brackets', function () {
    $engine=app(AlgerianPayrollEngine::class);$rules=dzRegulation()->rules['irg'];
    expect($engine->monthlyIrg(30000,$rules))->toBe(0.0)->and($engine->monthlyIrg(50000,$rules))->toBeGreaterThan(0);
});

test('calculation snapshots contain the regulation version and full rules', function () {
    $regulation=dzRegulation();$result=app(AlgerianPayrollEngine::class)->calculate(new Staff(),now(),[],$regulation);
    expect($result['breakdown']['regulation']['version'])->toBe('TEST-2026')->and($result['breakdown']['regulation']['rules'])->toBe($regulation->rules);
});

test('das exporter refuses to invent an unsupported official layout', function () {
    expect(app(CnasDasExporter::class)->supported())->toBeFalse();
});
