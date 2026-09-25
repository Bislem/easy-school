<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinancialAccount extends Model
{
    protected $fillable = ['accountable_type', 'accountable_id', 'domain', 'student_id', 'academic_year_id', 'school_fee_structure_id', 'expected_total', 'paid_total', 'adjustment_total', 'balance', 'status'];

    protected $casts = ['expected_total' => 'decimal:2', 'paid_total' => 'decimal:2', 'adjustment_total' => 'decimal:2', 'balance' => 'decimal:2'];

    public function accountable(): MorphTo
    {
        return $this->morphTo();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(SchoolFeeStructure::class, 'school_fee_structure_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(FinancialInstallment::class)->orderBy('sort_order');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class)->latest('transaction_date');
    }
}
