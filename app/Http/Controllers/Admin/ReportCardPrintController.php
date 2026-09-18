<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{CompanySetting,ReportCard};
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
class ReportCardPrintController extends Controller {
 public function __invoke(Request $request, ReportCard $reportCard) { abort_unless((int)$reportCard->tenant_id === (int)app(\App\Tenancy\TenantContext::class)->id(),404); abort_unless(in_array($reportCard->status,['validated','published','locked'],true),422,'Seuls les bulletins validés peuvent être imprimés.'); $reportCard->load(['student','year','level','group','subjects']); $pdf=Pdf::loadView('admin.report-cards.bulletin',['card'=>$reportCard,'school'=>CompanySetting::current()])->setPaper('a4','portrait'); $name='bulletin_'.str($reportCard->student?->first_name.'-'.$reportCard->student?->last_name)->slug('-').'_'.$reportCard->year?->name.'_'.$reportCard->period_key.'.pdf'; return $request->boolean('inline')?$pdf->stream($name):$pdf->download($name); }
}
