<?php

namespace App\Models;

use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryConfiguration extends Model
{
    protected $fillable = ['staff_id','salary_type','base_rate','effective_from','effective_to','notes'];
    protected $casts = ['salary_type'=>SalaryType::class,'base_rate'=>'decimal:2','effective_from'=>'date:Y-m-d','effective_to'=>'date:Y-m-d'];
    public function staff(): BelongsTo { return $this->belongsTo(Staff::class); }
    public function statements(): HasMany { return $this->hasMany(SalaryStatement::class); }
}
