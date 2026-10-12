<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class EnrollmentForm extends Model
{
    use HasFiles;

    protected $appends = ['cover_url'];

    protected $fillable = [
        'course_id', 'teacher_id', 'classroom_id', 'title', 'start_date', 'end_date',
        'min_students', 'max_students', 'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $form) => $form->public_token ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d',
            'min_students' => 'integer', 'max_students' => 'integer', 'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'course_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function trainingPlans(): HasMany
    {
        return $this->hasMany(TrainingPlan::class);
    }

    public function pricingConfigs(): HasMany
    {
        return $this->hasMany(FormationPricingConfig::class);
    }

    public function registeredCount(): int
    {
        return $this->enrollments()->where('status', 'registered')->count();
    }

    /** Public registration is open while the session is active, not ended, not full and its formation is active. */
    public function acceptsApplications(): bool
    {
        $this->loadMissing('course');

        return $this->is_active && $this->course?->is_active && ! $this->end_date?->lt(today())
            && $this->registeredCount() < $this->max_students;
    }

    public function getCoverUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'cover')
            : $this->files()->where('collection', 'cover')->first();

        return $file?->url;
    }
}
