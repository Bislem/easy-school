<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\PaymentMethod;
use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Services\FinancialAccountService;
use App\Services\FinancialReceiptService;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Payment, movement, cancellation and receipt actions shared by the school and formation billing screens. */
trait HandlesFinancialAccounts
{
    abstract protected function domain(): string;

    public function pay(Request $request, FinancialAccount $account, FinancialAccountService $ledger): RedirectResponse
    {
        $this->ensureDomain($account);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'transaction_date' => ['required', 'date', 'before_or_equal:today'], 'payment_method' => ['required', Rule::enum(PaymentMethod::class)], 'reference' => ['nullable', 'string', 'max:100', TenantRule::unique('financial_transactions', 'reference')], 'external_reference' => ['nullable', 'string', 'max:150'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $ledger->recordPayment($account, $data, $request->user()->id);

        return back()->with('success', 'Paiement enregistré et imputé sur l’échéancier.');
    }

    public function movement(Request $request, FinancialAccount $account, FinancialAccountService $ledger): RedirectResponse
    {
        $this->ensureDomain($account);
        $data = $request->validate(['type' => ['required', Rule::in(FinancialAccountService::MOVEMENT_TYPES)], 'amount' => ['required', 'numeric', 'min:0.01'], 'reason' => ['required', 'string', 'max:1000'], 'due_date' => ['nullable', 'date']]);
        $ledger->recordMovement($account, $data['type'], $data['amount'], $data['reason'], $request->user()->id, $data['due_date'] ?? null);

        return back()->with('success', 'Mouvement financier enregistré.');
    }

    public function cancel(Request $request, FinancialTransaction $transaction, FinancialAccountService $ledger): RedirectResponse
    {
        $this->ensureDomain($transaction->account);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $ledger->cancel($transaction, $data['reason'], $request->user()->id);

        return back()->with('success', 'Opération annulée ; l’écriture d’origine est conservée.');
    }

    public function receipt(FinancialTransaction $transaction, FinancialReceiptService $receipts): Response
    {
        $this->ensureDomain($transaction->account);

        return $receipts->download($transaction);
    }

    protected function ensureDomain(?FinancialAccount $account): void
    {
        abort_unless($account?->domain === $this->domain(), 404);
    }

    protected function paymentMethods(): array
    {
        return collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])->all();
    }
}
