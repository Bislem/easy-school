<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EnrollmentFinancialAdjustment extends Model
{
    protected $fillable = ['course_enrollment_id','type','amount','reason','created_by'];
    protected $casts = ['amount'=>'decimal:2'];
    public function enrollment(): BelongsTo { return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
