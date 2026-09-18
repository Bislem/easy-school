<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ReportCard extends Model {
    protected $fillable = ['tenant_id','academic_year_id','student_id','student_academic_enrollment_id','school_level_id','school_group_id','period_key','status','source_data_changed','general_average','class_average','rank','total_students','teacher_comment','administration_comment','absences_count','late_count','generated_at','validated_at','published_at','validated_by','published_by','locked_by','locked_at'];
    protected function casts(): array { return ['source_data_changed'=>'boolean','general_average'=>'decimal:2','class_average'=>'decimal:2','generated_at'=>'datetime','validated_at'=>'datetime','published_at'=>'datetime','locked_at'=>'datetime']; }
    public function enrollment(): BelongsTo { return $this->belongsTo(StudentAcademicEnrollment::class, 'student_academic_enrollment_id'); }
    public function year(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'academic_year_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function level(): BelongsTo { return $this->belongsTo(SchoolLevel::class, 'school_level_id'); }
    public function group(): BelongsTo { return $this->belongsTo(SchoolGroup::class, 'school_group_id'); }
    public function subjects(): HasMany { return $this->hasMany(ReportCardSubject::class); }
}
