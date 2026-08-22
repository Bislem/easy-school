<?php
namespace App\Models;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
class StudentPayment extends Model
{
    protected $fillable = ['reference','student_id','course_enrollment_id','student_installment_id','amount','payment_date','payment_method','status','recorded_by','reverses_payment_id','previous_balance','remaining_balance','notes'];
    protected $casts = ['amount'=>'decimal:2','previous_balance'=>'decimal:2','remaining_balance'=>'decimal:2','payment_date'=>'date:Y-m-d','payment_method'=>PaymentMethod::class];
    protected static function booted(): void { static::updating(fn()=>throw new LogicException('Student payment history is immutable.')); static::deleting(fn()=>throw new LogicException('Student payment history is immutable.')); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function enrollment(): BelongsTo { return $this->belongsTo(CourseEnrollment::class, 'course_enrollment_id'); }
    public function installment(): BelongsTo { return $this->belongsTo(StudentInstallment::class, 'student_installment_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function reversedPayment(): BelongsTo { return $this->belongsTo(self::class, 'reverses_payment_id'); }
}
