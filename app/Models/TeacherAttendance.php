<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
class TeacherAttendance extends Model
{
    protected $fillable=['training_session_id','scheduled_teacher_id','actual_teacher_id','status','arrival_time','departure_time','worked_minutes','is_justified','justification','notes','recorded_by','validated_at','validated_by'];
    protected $casts=['is_justified'=>'boolean','validated_at'=>'datetime','worked_minutes'=>'integer'];
    public function session():BelongsTo{return $this->belongsTo(TrainingSession::class,'training_session_id');}
    public function scheduledTeacher():BelongsTo{return $this->belongsTo(User::class,'scheduled_teacher_id');}
    public function actualTeacher():BelongsTo{return $this->belongsTo(User::class,'actual_teacher_id');}
    public function histories():MorphMany{return $this->morphMany(AttendanceHistory::class,'attendance');}
}
