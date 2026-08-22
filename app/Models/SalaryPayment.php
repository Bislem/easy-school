<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SalaryPayment extends Model
{
    protected $fillable = ['salary_statement_id','staff_id','expense_id','amount','paid_at','payment_method','reference','notes','created_by'];
    protected $casts = ['amount'=>'decimal:2','paid_at'=>'datetime'];
    protected static function booted(): void {
        static::updating(fn () => throw new LogicException('Salary payment history is immutable.'));
        static::deleting(fn () => throw new LogicException('Salary payment history is immutable.'));
    }
    public function statement(): BelongsTo { return $this->belongsTo(SalaryStatement::class,'salary_statement_id'); }
    public function staff(): BelongsTo { return $this->belongsTo(Staff::class); }
    public function expense(): BelongsTo { return $this->belongsTo(Expense::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
}
