<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'enrollment_form_id', 'student_id', 'first_name', 'last_name', 'email',
        'phone', 'birth_date', 'confirmation_token', 'confirmed_at', 'group_number',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date:Y-m-d', 'confirmed_at' => 'datetime', 'group_number' => 'integer'];
    }

    public function form(): BelongsTo { return $this->belongsTo(EnrollmentForm::class, 'enrollment_form_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
