<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolLevel extends Model
{
    protected $fillable = ['school_cycle_id', 'name', 'code', 'specialization', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SchoolCycle::class, 'school_cycle_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(SchoolGroup::class);
    }

    public function privateSchoolInscriptions(): HasMany
    {
        return $this->hasMany(PrivateSchoolInscription::class);
    }

    public function subjects(): BelongsToMany
    {
        $relation = $this->belongsToMany(SchoolSubject::class, 'course_school_level')->withPivot(['school_stream_id', 'curriculum_code', 'is_optional', 'is_active', 'display_order', 'choice_group'])->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }
}
