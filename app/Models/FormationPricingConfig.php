<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormationPricingConfig extends Model
{
    protected $fillable = ['course_id', 'enrollment_form_id', 'training_plan_group_id', 'name', 'total_price', 'is_active'];

    protected $casts = ['total_price' => 'decimal:2', 'is_active' => 'boolean'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'course_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EnrollmentForm::class, 'enrollment_form_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanGroup::class, 'training_plan_group_id');
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(FormationPricingScheduleItem::class)->orderBy('sort_order');
    }
}
