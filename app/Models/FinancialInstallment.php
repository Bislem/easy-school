<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialInstallment extends Model
{
    protected $fillable = ['financial_account_id', 'school_fee_component_id', 'source_key', 'label', 'amount', 'due_date', 'sort_order', 'status'];

    protected $casts = ['amount' => 'decimal:2', 'due_date' => 'date:Y-m-d', 'sort_order' => 'integer'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FinancialAllocation::class);
    }
}
