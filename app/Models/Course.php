<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'code',
        'category',
        'duration_hours',
        'price',
        'description',
        'objectives',
        'prerequisites',
        'required_room_types',
        'is_certified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'integer',
            'price' => 'decimal:2',
            'is_certified' => 'boolean',
            'is_active' => 'boolean',
            'required_room_types' => 'array',
        ];
    }

    public function levels(): HasMany { return $this->hasMany(CourseLevel::class); }
    public function schoolLevels(): BelongsToMany { return $this->belongsToMany(SchoolLevel::class, 'course_school_level')->withPivotValue('tenant_id', app(\App\Tenancy\TenantContext::class)->id())->withTimestamps(); }
    public function teachers(): BelongsToMany { return $this->belongsToMany(User::class, 'course_teacher', 'course_id', 'teacher_id')->withPivotValue('tenant_id', app(\App\Tenancy\TenantContext::class)->id())->withTimestamps(); }
    public function timetableSessions(): HasMany { return $this->hasMany(TimetableSession::class); }
}
