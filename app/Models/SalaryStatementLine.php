<?php

namespace App\Models;

use App\Enums\SalaryCalculationType;
use App\Enums\SalaryItemCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStatementLine extends Model
{
    protected $fillable = ['salary_statement_id', 'salary_item_id', 'item_name', 'item_code', 'category', 'calculation_type', 'quantity', 'rate', 'amount', 'source', 'display_order', 'snapshot_data'];
    protected $casts = ['category' => SalaryItemCategory::class, 'calculation_type' => SalaryCalculationType::class, 'quantity' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2', 'snapshot_data' => 'array'];
    public function statement(): BelongsTo { return $this->belongsTo(SalaryStatement::class, 'salary_statement_id'); }
    public function salaryItem(): BelongsTo { return $this->belongsTo(SalaryItem::class); }
}
