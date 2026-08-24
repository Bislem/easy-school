<?php

namespace App\Models;

use App\Enums\SalaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SalaryStatement extends Model
{
    protected $fillable = ['staff_id','salary_configuration_id','reference','period_start','period_end','salary_type','base_rate','units','gross_salary','bonuses','deductions','advances','exceptional_payments','reimbursements','net_salary','amount_paid','remaining_amount','status','calculation_details','notes','generated_by'];
    protected $casts = ['salary_type'=>SalaryType::class,'period_start'=>'date:Y-m-d','period_end'=>'date:Y-m-d','base_rate'=>'decimal:2','units'=>'decimal:2','gross_salary'=>'decimal:2','bonuses'=>'decimal:2','deductions'=>'decimal:2','advances'=>'decimal:2','exceptional_payments'=>'decimal:2','reimbursements'=>'decimal:2','net_salary'=>'decimal:2','amount_paid'=>'decimal:2','remaining_amount'=>'decimal:2','calculation_details'=>'array'];
    public function staff(): BelongsTo { return $this->belongsTo(Staff::class); }
    public function configuration(): BelongsTo { return $this->belongsTo(SalaryConfiguration::class,'salary_configuration_id'); }
    public function payments(): HasMany { return $this->hasMany(SalaryPayment::class); }
    public function adjustments(): HasMany { return $this->hasMany(SalaryAdjustment::class); }
    public function generator(): BelongsTo { return $this->belongsTo(User::class,'generated_by'); }
    public function teacherAttendances(): BelongsToMany { return $this->belongsToMany(TeacherAttendance::class,'salary_statement_teacher_attendances')->withTimestamps(); }
}
