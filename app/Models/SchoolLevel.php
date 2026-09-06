<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolLevel extends Model
{
    protected $fillable = ['school_cycle_id', 'name', 'code', 'specialization', 'sort_order', 'is_active'];
    protected function casts(): array { return ['sort_order' => 'integer', 'is_active' => 'boolean']; }
    public function cycle(): BelongsTo { return $this->belongsTo(SchoolCycle::class, 'school_cycle_id'); }
    public function groups(): HasMany { return $this->hasMany(TrainingPlanGroup::class); }
    public function subjects(): BelongsToMany { return $this->belongsToMany(Course::class, 'course_school_level')->withPivotValue('tenant_id', app(\App\Tenancy\TenantContext::class)->id())->withTimestamps(); }
}
