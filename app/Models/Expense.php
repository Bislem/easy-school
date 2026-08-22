<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class Expense extends Model
{
    use HasFiles, SoftDeletes;

    protected $appends = ['receipt_url'];

    protected $fillable = [
        'created_by', 'employee_id', 'staff_id', 'type', 'category', 'title', 'amount',
        'expense_date', 'salary_period', 'vendor', 'payment_method', 'reference', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date:Y-m-d',
        'salary_period' => 'date:Y-m-d',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function salaryPayment(): HasOne { return $this->hasOne(SalaryPayment::class); }

    public function getReceiptUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'receipt')
            : $this->files()->where('collection', 'receipt')->first();

        return $file?->url;
    }
}
