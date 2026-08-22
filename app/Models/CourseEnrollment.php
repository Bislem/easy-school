<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\EnrollmentPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'enrollment_form_id', 'training_plan_group_id', 'student_id', 'status', 'first_name', 'last_name', 'email',
        'phone', 'parent_phone', 'birth_date', 'level', 'confirmation_token', 'confirmed_at', 'group_number', 'notes',
        'contacted_at', 'approved_at', 'registered_at', 'rejected_at', 'cancelled_at',
        'formation_price', 'discount_amount', 'adjustment_total', 'final_price', 'total_paid', 'remaining_balance', 'payment_status',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date:Y-m-d', 'confirmed_at' => 'datetime', 'group_number' => 'integer',
            'status' => ApplicationStatus::class, 'contacted_at' => 'datetime', 'approved_at' => 'datetime',
            'registered_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime',
            'formation_price'=>'decimal:2', 'discount_amount'=>'decimal:2', 'adjustment_total'=>'decimal:2', 'final_price'=>'decimal:2',
            'total_paid'=>'decimal:2', 'remaining_balance'=>'decimal:2', 'payment_status'=>EnrollmentPaymentStatus::class];
    }

    protected static function booted(): void
    {
        static::creating(function (self $enrollment) {
            if ($enrollment->formation_price === null && $enrollment->enrollment_form_id) {
                $price = EnrollmentForm::with('course:id,price')->find($enrollment->enrollment_form_id)?->course?->price;
                $enrollment->formation_price = $price ?? 0;
                $enrollment->final_price = $price ?? 0;
                $enrollment->remaining_balance = $price ?? 0;
            }
        });
    }

    public function form(): BelongsTo { return $this->belongsTo(EnrollmentForm::class, 'enrollment_form_id'); }
    public function trainingPlanGroup(): BelongsTo { return $this->belongsTo(TrainingPlanGroup::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function histories(): HasMany { return $this->hasMany(EnrollmentHistory::class)->latest(); }
    public function installments(): HasMany { return $this->hasMany(StudentInstallment::class)->orderBy('due_date'); }
    public function payments(): HasMany { return $this->hasMany(StudentPayment::class)->latest('payment_date'); }
    public function financialAdjustments(): HasMany { return $this->hasMany(EnrollmentFinancialAdjustment::class)->latest(); }
    public function certificates(): HasMany { return $this->hasMany(Certificate::class)->latest('issue_date'); }
}
