<?php

namespace App\Models;

use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SalaryConfiguration extends Model
{
    protected $fillable = ['name','salary_type','base_rate','effective_from','effective_to','notes'];
    protected $casts = ['salary_type'=>SalaryType::class,'base_rate'=>'decimal:2','effective_from'=>'date:Y-m-d','effective_to'=>'date:Y-m-d'];
    public function statements(): HasMany { return $this->hasMany(SalaryStatement::class); }
    public function items(): BelongsToMany { return $this->belongsToMany(SalaryItem::class)->withPivot(['amount_override','display_order'])->withTimestamps()->orderByPivot('display_order'); }
}
