<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAllocation extends Model
{
    protected $fillable = ['financial_transaction_id', 'financial_installment_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(FinancialInstallment::class, 'financial_installment_id');
    }
}
