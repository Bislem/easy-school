<?php

namespace App\Console\Commands;

use App\Models\FinancialAccount;
use App\Models\FinancialInstallment;
use App\Services\FinancialAccountService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

/** Installments become overdue with the passage of time, not only when an account is written to. */
final class RefreshFinancialStatuses extends Command
{
    protected $signature = 'finance:refresh-statuses';

    protected $description = 'Mark unpaid installments past their due date as overdue and refresh account totals';

    public function handle(FinancialAccountService $ledger, TenantContext $context): int
    {
        $accountIds = FinancialInstallment::withoutGlobalScopes()->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', today())->distinct()->pluck('financial_account_id');
        FinancialAccount::withoutGlobalScopes()->whereIn('id', $accountIds)->each(
            fn (FinancialAccount $account) => $context->runAs((int) $account->tenant_id, fn () => $ledger->refresh($account))
        );
        $this->info("{$accountIds->count()} compte(s) actualisé(s).");

        return self::SUCCESS;
    }
}
