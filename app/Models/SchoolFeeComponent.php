<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolFeeComponent extends Model
{
    protected $fillable = ['school_fee_structure_id', 'code', 'label', 'amount', 'is_mandatory', 'sort_order'];

    protected $casts = ['amount' => 'decimal:2', 'is_mandatory' => 'boolean', 'sort_order' => 'integer'];

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SchoolFeeStructure::class, 'school_fee_structure_id');
    }
}
