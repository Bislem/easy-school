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
        'min_students', 'max_students', 'groups_count', 'is_active',
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
            'groups_count' => 'integer', 'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function enrollments(): HasMany { return $this->hasMany(CourseEnrollment::class); }

    public function getCoverUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('files')
            ? $this->files->firstWhere('collection', 'cover')
            : $this->files()->where('collection', 'cover')->first();

        return $file?->url;
    }
}
