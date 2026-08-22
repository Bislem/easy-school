<?php

namespace App\Http\Controllers;

use App\Models\SalaryPayment;
use App\Models\SalaryStatement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryPortalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $staff=$request->user()->staff;
        abort_unless($staff,403,'Aucun profil employé lié.');
        return Inertia::render('Salary/Index',['employee'=>$staff->load('employeeType'),'statements'=>SalaryStatement::where('staff_id',$staff->id)->with(['payments','adjustments'])->latest('period_end')->paginate(15),'payments'=>SalaryPayment::where('staff_id',$staff->id)->latest('paid_at')->get(),'currency'=>config('app.currency_symbol')]);
    }
}
