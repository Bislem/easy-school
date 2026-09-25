<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormationPricingScheduleItem extends Model
{
    protected $fillable = ['formation_pricing_config_id', 'label', 'amount', 'due_date', 'sort_order'];

    protected $casts = ['amount' => 'decimal:2', 'due_date' => 'date:Y-m-d', 'sort_order' => 'integer'];

    public function pricing(): BelongsTo
    {
        return $this->belongsTo(FormationPricingConfig::class, 'formation_pricing_config_id');
    }
}
