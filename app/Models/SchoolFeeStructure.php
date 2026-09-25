<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolFeeStructure extends Model
{
    protected $fillable = ['academic_year_id', 'school_level_id', 'school_group_id', 'student_id', 'name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(SchoolGroup::class, 'school_group_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(SchoolFeeComponent::class)->orderBy('sort_order');
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(SchoolFeeScheduleItem::class)->orderBy('sort_order');
    }
}
