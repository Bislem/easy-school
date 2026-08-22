<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SessionAttendance extends Model {protected $fillable=['training_session_id','student_id','course_enrollment_id','status','arrival_time','departure_time','is_justified','justification','recorded_at','recorded_by','notes'];protected $casts=['recorded_at'=>'datetime','is_justified'=>'boolean'];public function session():BelongsTo{return $this->belongsTo(TrainingSession::class,'training_session_id');}public function student():BelongsTo{return $this->belongsTo(Student::class);}public function enrollment():BelongsTo{return $this->belongsTo(CourseEnrollment::class,'course_enrollment_id');}public function histories():\Illuminate\Database\Eloquent\Relations\MorphMany{return $this->morphMany(AttendanceHistory::class,'attendance');}}
