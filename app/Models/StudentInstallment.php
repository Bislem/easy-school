<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class StudentInstallment extends Model
{
    protected $fillable = ['course_enrollment_id', 'amount', 'due_date', 'status', 'paid_date', 'notes'];
    protected $casts = ['amount'=>'decimal:2', 'due_date'=>'date:Y-m-d', 'paid_date'=>'date:Y-m-d'];
    public function enrollment(): BelongsTo { return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id'); }
    public function payments(): HasMany { return $this->hasMany(StudentPayment::class); }
}
