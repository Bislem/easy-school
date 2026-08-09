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
        'min_students', 'max_students', 'groups_count', 'students_per_group', 'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $form) => $form->public_token ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d',
            'min_students' => 'integer', 'max_students' => 'integer',
            'groups_count' => 'integer', 'students_per_group' => 'integer', 'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function enrollments(): HasMany { return $this->hasMany(CourseEnrollment::class); }

    public function groupCapacity(): int
    {
        if ($this->classroom_id) {
            $this->loadMissing('classroom');

            return (int) $this->classroom->capacity;
        }

        return (int) ($this->students_per_group
            ?: max(1, (int) ceil($this->max_students / max(1, $this->groups_count))));
    }

    public function nextAvailableGroup(): ?int
    {
        $counts = $this->enrollments()->whereNotNull('confirmed_at')
            ->selectRaw('group_number, COUNT(*) as total')
            ->groupBy('group_number')
            ->pluck('total', 'group_number');
        $capacity = $this->groupCapacity();

        return collect(range(1, $this->groups_count))
            ->filter(fn (int $group) => (int) ($counts[$group] ?? 0) < $capacity)
            ->sortBy(fn (int $group) => (int) ($counts[$group] ?? 0))
            ->first();
    }

    public function getCoverUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'cover')
            : $this->files()->where('collection', 'cover')->first();

        return $file?->url;
    }
}
