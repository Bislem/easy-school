<?php

namespace App\Models;

use App\Enums\AcademicYearStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicYear extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'status', 'notes'];

    protected function casts(): array
    {
        return ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'status' => AcademicYearStatus::class];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AcademicPeriod::class)->orderBy('number');
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(AcademicYearCalendarEvent::class)->orderBy('starts_on');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(SchoolGroup::class);
    }

    public function studentEnrollments(): HasMany
    {
        return $this->hasMany(StudentAcademicEnrollment::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherAcademicAssignment::class);
    }

    public function inscriptionCampaigns(): HasMany
    {
        return $this->hasMany(PrivateSchoolInscriptionCampaign::class);
    }

    public function privateSchoolInscriptions(): HasMany
    {
        return $this->hasMany(PrivateSchoolInscription::class);
    }

    public function timetableSessions(): HasMany
    {
        return $this->hasMany(TimetableSession::class);
    }

    public function isWritable(): bool
    {
        return in_array($this->status, [AcademicYearStatus::DRAFT, AcademicYearStatus::ACTIVE], true);
    }
}
