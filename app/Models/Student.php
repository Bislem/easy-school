<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;
use App\Enums\StudentStatus;

class Student extends Model
{
    use HasFactory, HasFiles;

    protected $fillable = [
        'user_id', 'first_name',
        'last_name',
        'photo_path',
        'email',
        'phone',
        'parent_phone',
        'birth_date',
        'address',
        'registration_date', 'school_level', 'status',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'registration_date' => 'date:Y-m-d',
            'status' => StudentStatus::class,
            'is_active' => 'boolean',
        ];
    }

    protected $appends = ['full_name', 'photo_url'];

    public function enrollments(): HasMany { return $this->hasMany(CourseEnrollment::class); }
    public function payments(): HasMany { return $this->hasMany(StudentPayment::class)->latest('payment_date'); }
    public function histories(): HasMany { return $this->hasMany(StudentHistory::class)->latest(); }
    public function badges(): \Illuminate\Database\Eloquent\Relations\MorphMany { return $this->morphMany(Badge::class, 'badgeable')->latest('issue_date'); }
    public function parents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(SchoolParent::class,'parent_student','student_id','parent_id')->withPivot('is_primary')->withTimestamps(); }
    public function attendances(): HasMany { return $this->hasMany(SessionAttendance::class); }
    public function certificates(): HasMany { return $this->hasMany(Certificate::class)->latest('issue_date'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function getPhotoUrlAttribute(): ?string { return $this->photo_path ? asset('storage/'.$this->photo_path) : null; }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
