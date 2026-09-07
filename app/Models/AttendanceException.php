<?php

namespace App\Models;

use App\Enums\AttendanceExceptionStatus;
use App\Enums\AttendancePersonType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceException extends Model
{
    protected $fillable = ['academic_year_id', 'date', 'end_date', 'timetable_session_id', 'person_type', 'student_id', 'teacher_id', 'status', 'minutes_late', 'reason', 'justification', 'justified_at', 'created_by', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'justified_at' => 'datetime', 'person_type' => AttendancePersonType::class, 'status' => AttendanceExceptionStatus::class, 'minutes_late' => 'integer'];
    }

    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function timetableSession(): BelongsTo { return $this->belongsTo(TimetableSession::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
