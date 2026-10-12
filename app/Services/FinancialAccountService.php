<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\FinancialInstallment;
use App\Models\FinancialTransaction;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ledger rules shared by school and formation billing.
 *
 * Installments are the amounts due. Payments (cash) and credits (discount,
 * scholarship, waiver) settle installments through allocations, so the sum of
 * open installment amounts is always the account balance. A debit adjustment
 * adds a new installment. Cash movements are the only rows with a payment
 * method; cancelling a transaction mirrors its allocations negatively.
 */
final class FinancialAccountService
{
    public const CREDIT_TYPES = ['discount', 'scholarship'];

    public const MOVEMENT_TYPES = ['discount', 'scholarship', 'adjustment'];

    public function refresh(FinancialAccount $account): FinancialAccount
    {
        $transactions = $account->transactions()->withoutGlobalScope(AuthorizationService::DATA_SCOPE)->reorder()->get(['id', 'type', 'amount', 'payment_method']);
        $paid = $transactions->whereNotNull('payment_method')->sum(fn ($t) => Money::cents($t->amount));
        $credits = $transactions->whereNull('payment_method')->where('type', '!=', 'adjustment')->sum(fn ($t) => Money::cents($t->amount));
        $expected = 0;
        $balance = 0;
        $overdue = false;
        foreach ($this->installments($account) as $installment) {
            $open = $this->open($installment);
            $expected += Money::cents($installment->amount);
            $balance += $open;
            $status = $open === 0 ? 'paid' : ($installment->due_date->lt(today()) ? 'overdue' : ($open < Money::cents($installment->amount) ? 'partial' : 'pending'));
            $overdue = $overdue || $status === 'overdue';
            if ($installment->status !== $status) {
                $installment->updateQuietly(['status' => $status]);
            }
        }
        $status = $balance === 0 ? 'paid' : ($overdue ? 'overdue' : ($paid > 0 || $credits < 0 ? 'partial' : 'unpaid'));
        $account->updateQuietly(['expected_total' => Money::amount($expected), 'paid_total' => Money::amount($paid), 'adjustment_total' => Money::amount($credits), 'balance' => Money::amount($balance), 'status' => $status]);

        return $account->fresh();
    }

    public function recordPayment(FinancialAccount $account, array $data, int $userId): FinancialTransaction
    {
        return DB::transaction(function () use ($account, $data, $userId) {
            $account = $this->lock($account);
            $this->ensureOpen($account);
            $amount = Money::cents($data['amount']);
            if ($amount <= 0 || $amount > Money::cents($account->balance)) {
                throw ValidationException::withMessages(['amount' => 'Le montant dépasse le solde restant.']);
            }
            $transaction = $account->transactions()->create(['reference' => ($data['reference'] ?? null) ?: $this->reference('PAY'), 'type' => 'payment', 'amount' => Money::amount($amount), 'transaction_date' => $data['transaction_date'], 'payment_method' => $data['payment_method'], 'external_reference' => $data['external_reference'] ?? null, 'notes' => $data['notes'] ?? null, 'recorded_by' => $userId]);
            $this->allocate($transaction, $this->installments($account), $amount);
            $this->refresh($account);

            return $transaction;
        });
    }

    /** Discounts and scholarships settle the latest installments first; adjustments add a new installment. */
    public function recordMovement(FinancialAccount $account, string $type, float|string $amount, string $reason, int $userId, ?string $dueDate = null): FinancialTransaction
    {
        abort_unless(in_array($type, self::MOVEMENT_TYPES, true), 422);

        return DB::transaction(function () use ($account, $type, $amount, $reason, $userId, $dueDate) {
            $account = $this->lock($account);
            $this->ensureOpen($account);
            $cents = Money::cents($amount);
            if ($cents <= 0) {
                throw ValidationException::withMessages(['amount' => 'Le montant doit être positif.']);
            }
            if ($type !== 'adjustment' && $cents > Money::cents($account->balance)) {
                throw ValidationException::withMessages(['amount' => 'La remise dépasse le solde restant.']);
            }
            $signed = $type === 'adjustment' ? $cents : -$cents;
            $transaction = $account->transactions()->create(['reference' => $this->reference(strtoupper(substr($type, 0, 3))), 'type' => $type, 'amount' => Money::amount($signed), 'transaction_date' => today(), 'reason' => $reason, 'recorded_by' => $userId]);
            if ($type === 'adjustment') {
                $sortOrder = (int) $account->installments()->max('sort_order') + 1;
                $account->installments()->create(['source_key' => 'adjustment-'.$transaction->id, 'label' => 'Ajustement : '.str($reason)->limit(120), 'amount' => Money::amount($cents), 'due_date' => $dueDate ?? today(), 'sort_order' => $sortOrder]);
            } else {
                $this->allocate($transaction, $this->installments($account)->reverse()->values(), $cents);
            }
            $this->refresh($account);

            return $transaction;
        });
    }

    /** Cancel a payment or a credit by recording an opposite entry; history is never edited. */
    public function cancel(FinancialTransaction $original, string $reason, int $userId): FinancialTransaction
    {
        return DB::transaction(function () use ($original, $reason, $userId) {
            $account = $this->lock($original->account);
            $this->ensureOpen($account);
            if (! in_array($original->type, ['payment', ...self::CREDIT_TYPES], true)) {
                throw ValidationException::withMessages(['transaction' => 'Seuls les paiements, remises et bourses peuvent être annulés.']);
            }
            if (FinancialTransaction::withoutGlobalScope(AuthorizationService::DATA_SCOPE)->where('reverses_transaction_id', $original->id)->exists()) {
                throw ValidationException::withMessages(['transaction' => 'Cette opération a déjà été annulée.']);
            }

            return $this->reverse($account, $original, $reason, $userId);
        });
    }

    /** Close an account when its enrollment ends: open installments not yet due are waived. */
    public function close(FinancialAccount $account, string $reason, ?int $userId = null): FinancialAccount
    {
        return DB::transaction(function () use ($account, $reason, $userId) {
            $account = $this->lock($account);
            if ($account->closed_at) {
                return $account;
            }
            $future = $this->installments($account)->filter(fn (FinancialInstallment $i) => $i->due_date->gte(today()));
            $waived = $future->sum(fn (FinancialInstallment $i) => $this->open($i));
            if ($waived > 0) {
                $transaction = $account->transactions()->create(['reference' => $this->reference('WAI'), 'type' => 'waiver', 'amount' => Money::amount(-$waived), 'transaction_date' => today(), 'reason' => $reason, 'recorded_by' => $userId]);
                $this->allocate($transaction, $future->values(), $waived);
            }
            $account->updateQuietly(['closed_at' => now(), 'closed_reason' => $reason]);

            return $this->refresh($account);
        });
    }

    public function reopen(FinancialAccount $account, ?int $userId = null): FinancialAccount
    {
        return DB::transaction(function () use ($account, $userId) {
            $account = $this->lock($account);
            if (! $account->closed_at) {
                return $account;
            }
            $waivers = $account->transactions()->withoutGlobalScope(AuthorizationService::DATA_SCOPE)->where('type', 'waiver')->whereNotIn('id', FinancialTransaction::withoutGlobalScope(AuthorizationService::DATA_SCOPE)->whereNotNull('reverses_transaction_id')->select('reverses_transaction_id'))->get();
            foreach ($waivers as $waiver) {
                $this->reverse($account, $waiver, 'Réouverture du compte', $userId);
            }
            $account->updateQuietly(['closed_at' => null, 'closed_reason' => null]);

            return $this->refresh($account);
        });
    }

    /** @return Collection<int, FinancialInstallment> ordered by due date (FIFO). */
    private function installments(FinancialAccount $account): Collection
    {
        return $account->installments()->reorder()->withSum('allocations', 'amount')->orderBy('due_date')->orderBy('sort_order')->orderBy('id')->get();
    }

    private function open(FinancialInstallment $installment): int
    {
        return max(0, Money::cents($installment->amount) - Money::cents($installment->allocations_sum_amount ?? 0));
    }

    private function allocate(FinancialTransaction $transaction, Collection $installments, int $cents): void
    {
        foreach ($installments as $installment) {
            if ($cents <= 0) {
                break;
            }
            $part = min($cents, $this->open($installment));
            if ($part <= 0) {
                continue;
            }
            // Credits are stored as negative transactions but settle installments positively.
            $transaction->allocations()->create(['financial_installment_id' => $installment->id, 'amount' => Money::amount($part)]);
            $installment->allocations_sum_amount = Money::amount(Money::cents($installment->allocations_sum_amount ?? 0) + $part);
            $cents -= $part;
        }
        if ($cents > 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant ne peut pas être imputé sur l’échéancier.']);
        }
    }

    private function reverse(FinancialAccount $account, FinancialTransaction $original, string $reason, ?int $userId): FinancialTransaction
    {
        $reversal = $account->transactions()->create(['reference' => 'ANN-'.$original->reference, 'type' => 'reversal', 'amount' => Money::amount(-Money::cents($original->amount)), 'transaction_date' => today(), 'payment_method' => $original->payment_method, 'reason' => $reason, 'recorded_by' => $userId, 'reverses_transaction_id' => $original->id]);
        foreach ($original->allocations as $allocation) {
            $reversal->allocations()->create(['financial_installment_id' => $allocation->financial_installment_id, 'amount' => Money::amount(-Money::cents($allocation->amount))]);
        }
        $this->refresh($account);

        return $reversal;
    }

    private function lock(FinancialAccount $account): FinancialAccount
    {
        $locked = FinancialAccount::withoutGlobalScope(AuthorizationService::DATA_SCOPE)->lockForUpdate()->findOrFail($account->id);

        return $this->refresh($locked);
    }

    private function ensureOpen(FinancialAccount $account): void
    {
        if ($account->closed_at) {
            throw ValidationException::withMessages(['account' => 'Ce compte est clôturé.']);
        }
    }

    private function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.str()->upper(str()->random(8));
    }
}
