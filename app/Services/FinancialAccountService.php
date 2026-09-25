<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinancialAccountService
{
    public function refresh(FinancialAccount $account): FinancialAccount
    {
        $paid = (float) $account->transactions()->whereIn('type', ['payment', 'refund', 'reversal'])->sum('amount');
        $adjustments = (float) $account->transactions()->whereIn('type', ['discount', 'scholarship', 'adjustment'])->sum('amount');
        $expected = (float) $account->installments()->sum('amount');
        $balance = max(0, $expected + $adjustments - $paid);
        foreach ($account->installments()->withSum('allocations', 'amount')->get() as $installment) {
            $allocated = (float) ($installment->allocations_sum_amount ?? 0);
            $status = $allocated >= (float) $installment->amount ? 'paid' : ($allocated > 0 ? 'partial' : ($installment->due_date->isPast() ? 'overdue' : 'pending'));
            $installment->updateQuietly(['status' => $status]);
        }
        $overdue = $account->installments()->where('status', 'overdue')->exists();
        $status = $balance <= 0 ? 'paid' : ($overdue ? 'overdue' : ($paid > 0 ? 'partial' : 'unpaid'));
        $account->updateQuietly(['expected_total' => $expected, 'paid_total' => $paid, 'adjustment_total' => $adjustments, 'balance' => $balance, 'status' => $status]);

        return $account->fresh();
    }

    public function recordPayment(FinancialAccount $account, array $data, int $userId): FinancialTransaction
    {
        return DB::transaction(function () use ($account, $data, $userId) {
            $locked = FinancialAccount::lockForUpdate()->findOrFail($account->id);
            $this->refresh($locked);
            $locked->refresh();
            $amount = (float) $data['amount'];
            if ($amount > (float) $locked->balance) {
                throw ValidationException::withMessages(['amount' => 'Le montant dépasse le solde restant.']);
            }
            $transaction = $locked->transactions()->create(['reference' => $data['reference'] ?? $this->reference('PAY'), 'type' => 'payment', 'amount' => $amount, 'transaction_date' => $data['transaction_date'], 'payment_method' => $data['payment_method'], 'external_reference' => $data['external_reference'] ?? null, 'notes' => $data['notes'] ?? null, 'recorded_by' => $userId]);
            $remaining = $amount;
            $installments = $locked->installments()->withSum('allocations', 'amount')->orderBy('due_date')->orderBy('sort_order')->get();
            foreach ($installments as $installment) {
                $open = max(0, (float) $installment->amount - (float) ($installment->allocations_sum_amount ?? 0));
                if ($open <= 0) {
                    continue;
                }$allocated = min($remaining, $open);
                $transaction->allocations()->create(['financial_installment_id' => $installment->id, 'amount' => $allocated]);
                $remaining -= $allocated;
                if ($remaining <= 0) {
                    break;
                }
            }
            $this->refresh($locked);

            return $transaction;
        });
    }

    public function recordMovement(FinancialAccount $account, string $type, float $amount, string $reason, int $userId): FinancialTransaction
    {
        return DB::transaction(function () use ($account, $type, $amount, $reason, $userId) {
            $account = FinancialAccount::lockForUpdate()->findOrFail($account->id);
            $this->refresh($account);
            $account->refresh();
            if ($type === 'refund' && $amount > (float) $account->paid_total) {
                throw ValidationException::withMessages(['amount' => 'Le remboursement dépasse le total encaissé.']);
            }
            $signed = in_array($type, ['discount', 'scholarship'], true) ? -$amount : $amount;
            if ($type === 'refund') {
                $signed = -$amount;
            }
            $transaction = $account->transactions()->create(['reference' => $this->reference(strtoupper(substr($type, 0, 3))), 'type' => $type, 'amount' => $signed, 'transaction_date' => today(), 'reason' => $reason, 'recorded_by' => $userId]);
            if ($type === 'refund') {
                $remaining = $amount;
                foreach ($account->installments()->withSum('allocations', 'amount')->orderByDesc('due_date')->get() as $installment) {
                    $allocated = max(0, (float) ($installment->allocations_sum_amount ?? 0));
                    if ($allocated <= 0) {
                        continue;
                    }$part = min($remaining, $allocated);
                    $transaction->allocations()->create(['financial_installment_id' => $installment->id, 'amount' => -$part]);
                    $remaining -= $part;
                    if ($remaining <= 0) {
                        break;
                    }
                }
            }
            $this->refresh($account);

            return $transaction;
        });
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str()->upper(str()->random(8));
    }
}
