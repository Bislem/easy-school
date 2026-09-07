<?php

namespace App\Models;

use App\Enums\SalaryCalculationType;
use App\Enums\SalaryItemCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SalaryItem extends Model
{
    protected $fillable = ['name', 'code', 'category', 'calculation_type', 'default_amount', 'active', 'description', 'subject_to_cnas', 'subject_to_irg', 'salary_item_nature', 'irg_treatment'];
    protected $casts = ['category' => SalaryItemCategory::class, 'calculation_type' => SalaryCalculationType::class, 'default_amount' => 'decimal:2', 'active' => 'boolean', 'subject_to_cnas' => 'boolean', 'subject_to_irg' => 'boolean'];

    public function configurations(): BelongsToMany
    {
        return $this->belongsToMany(SalaryConfiguration::class)->withPivot(['amount_override', 'display_order'])->withTimestamps();
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_salary_items')->withPivot(['salary_configuration_id', 'amount_override', 'source', 'display_order'])->withTimestamps();
    }
}
