<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolFeeScheduleItem extends Model
{
    protected $fillable = ['school_fee_structure_id', 'school_fee_component_id', 'label', 'amount', 'due_date', 'sort_order'];

    protected $casts = ['amount' => 'decimal:2', 'due_date' => 'date:Y-m-d', 'sort_order' => 'integer'];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SchoolFeeStructure::class, 'school_fee_structure_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SchoolFeeComponent::class, 'school_fee_component_id');
    }
}
