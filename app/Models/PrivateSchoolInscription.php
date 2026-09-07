<?php

namespace App\Models;

use App\Enums\PrivateSchoolInscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateSchoolInscription extends Model
{
    use Concerns\GuardsAcademicYearWrites;

    protected $fillable = ['academic_year_id', 'campaign_id', 'campaign_level_id', 'school_level_id', 'student_id', 'parent_id', 'status', 'review_notes', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['status' => PrivateSchoolInscriptionStatus::class, 'reviewed_at' => 'datetime'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PrivateSchoolInscriptionCampaign::class, 'campaign_id');
    }

    public function campaignLevel(): BelongsTo
    {
        return $this->belongsTo(PrivateSchoolCampaignLevel::class, 'campaign_level_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SchoolParent::class, 'parent_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
