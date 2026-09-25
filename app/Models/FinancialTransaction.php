<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class FinancialTransaction extends Model
{
    protected $fillable = ['financial_account_id', 'reference', 'type', 'amount', 'transaction_date', 'payment_method', 'external_reference', 'reason', 'notes', 'recorded_by', 'reverses_transaction_id'];

    protected $casts = ['amount' => 'decimal:2', 'transaction_date' => 'date:Y-m-d', 'payment_method' => PaymentMethod::class];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Financial transaction history is immutable.'));
        static::deleting(fn () => throw new LogicException('Financial transaction history is immutable.'));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FinancialAllocation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
