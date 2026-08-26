<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class Staff extends Model
{
    use HasFiles;

    protected $table = 'staff';

    protected $fillable = [
        'user_id', 'employee_type_id', 'first_name', 'last_name', 'photo_path',
        'phone', 'email', 'address', 'birth_date', 'hire_date', 'employment_status',
        'notes', 'employee_code', 'identification_type', 'identification_number',
        'identification_expires_at', 'identification_notes',
        'social_security_number', 'gender', 'place_of_birth', 'nationality', 'marital_status',
        'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'bank_account',
        'leave_opening_balance', 'leave_balance_as_of', 'leave_balance_note',
        'can_view_student_folders',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d', 'hire_date' => 'date:Y-m-d',
        'identification_expires_at' => 'date:Y-m-d', 'employment_status' => EmploymentStatus::class,
        'leave_opening_balance' => 'decimal:2', 'leave_balance_as_of' => 'date:Y-m-d',
        'can_view_student_folders' => 'boolean',
    ];

    protected $appends = ['name', 'photo_url', 'is_teacher'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(Expense::class, 'staff_id')->where('category', 'Salaire');
    }

    public function salaryStatements(): HasMany
    {
        return $this->hasMany(SalaryStatement::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest();
    }

    public function annualLeaves(): HasMany
    {
        return $this->hasMany(AnnualLeave::class)->latest('starts_at');
    }

    public function sickLeaves(): HasMany
    {
        return $this->hasMany(SickLeave::class)->latest('starts_at');
    }

    public function hrRecords(): HasMany
    {
        return $this->hasMany(EmployeeHrRecord::class)->latest('starts_at')->latest();
    }

    public function leaveBalanceAdjustments(): HasMany
    {
        return $this->hasMany(LeaveBalanceAdjustment::class)->latest();
    }

    public function badges(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Badge::class, 'badgeable')->latest('issue_date');
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    public function getIsTeacherAttribute(): bool
    {
        return (bool) $this->employeeType?->is_teacher;
    }
}
