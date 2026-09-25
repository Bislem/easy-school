<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\FinancialTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

final class FinancialReceiptService
{
    public function download(FinancialTransaction $transaction): Response
    {
        abort_unless($transaction->type === 'payment', 404);
        $transaction->load(['account.student', 'account.academicYear', 'account.accountable', 'recorder', 'allocations.installment']);
        $view = $transaction->account->domain === 'school' ? 'admin.school-payments.receipt' : 'admin.formation-payments.receipt';

        return Pdf::loadView($view, ['transaction' => $transaction, 'school' => CompanySetting::current(), 'currency' => config('app.currency_symbol')])->download($transaction->reference.'.pdf');
    }
}
