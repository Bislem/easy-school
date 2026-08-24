<?php

namespace App\Models;

use App\Enums\CertificateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = ['student_id','course_enrollment_id','course_id','course_level_id','type','certificate_number','verification_token','issue_date','student_name','formation_name','level','group_label','duration_hours','formation_start','formation_end','attendance_rate','result','signature_name','notes','metadata','issued_by'];
    protected $hidden = ['verification_token'];
    protected $casts = ['type'=>CertificateType::class,'issue_date'=>'date:Y-m-d','formation_start'=>'date:Y-m-d','formation_end'=>'date:Y-m-d','attendance_rate'=>'decimal:2','metadata'=>'array'];
    protected $appends = ['verification_url'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function enrollment(): BelongsTo { return $this->belongsTo(CourseEnrollment::class,'course_enrollment_id'); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function levelRecord(): BelongsTo { return $this->belongsTo(CourseLevel::class,'course_level_id'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class,'issued_by'); }
    public function getVerificationUrlAttribute(): string { return route('certificates.verify',$this->verification_token); }
}
