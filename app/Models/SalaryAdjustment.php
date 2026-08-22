<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAdjustment extends Model
{
    protected $fillable = ['salary_statement_id','type','label','amount','notes'];
    protected $casts = ['amount'=>'decimal:2'];
    public function statement(): BelongsTo { return $this->belongsTo(SalaryStatement::class,'salary_statement_id'); }
}
