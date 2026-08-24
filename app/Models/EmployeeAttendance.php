<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class EmployeeAttendance extends Model
{
    protected $fillable=['staff_id','attendance_date','status','check_in','check_out','worked_minutes','is_justified','justification','notes','recorded_by','locked_at','locked_by'];
    protected $casts=['attendance_date'=>'date:Y-m-d','is_justified'=>'boolean','locked_at'=>'datetime','worked_minutes'=>'integer'];
    public function staff():BelongsTo{return $this->belongsTo(Staff::class);}
    public function histories():MorphMany{return $this->morphMany(AttendanceHistory::class,'attendance');}
    public function salaryStatements():BelongsToMany{return $this->belongsToMany(SalaryStatement::class,'salary_statement_employee_attendances')->withTimestamps();}
}
