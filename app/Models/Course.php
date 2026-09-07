<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'title_ar',
        'code',
        'category',
        'color',
        'duration_hours',
        'weekly_hours',
        'price',
        'description',
        'objectives',
        'prerequisites',
        'required_room_types',
        'is_specialized',
        'is_certified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'integer',
            'weekly_hours' => 'decimal:2',
            'price' => 'decimal:2',
            'is_certified' => 'boolean',
            'is_active' => 'boolean',
            'required_room_types' => 'array',
            'is_specialized' => 'boolean',
        ];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(CourseLevel::class, 'course_id');
    }

    public function schoolLevels(): BelongsToMany
    {
        $relation = $this->belongsToMany(SchoolLevel::class, 'course_school_level', 'course_id', 'school_level_id')->withPivot(['id', 'school_stream_id', 'curriculum_code', 'is_optional', 'is_active', 'display_order', 'choice_group'])->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }

    public function teachers(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class, 'course_teacher', 'course_id', 'teacher_id')->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }

    public function timetableSessions(): HasMany
    {
        return $this->hasMany(TimetableSession::class, 'course_id');
    }
}
