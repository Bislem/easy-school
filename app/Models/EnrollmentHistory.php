<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentHistory extends Model
{
    protected $fillable = ['course_enrollment_id', 'user_id', 'event', 'from_status', 'to_status', 'description', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function enrollment(): BelongsTo { return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
