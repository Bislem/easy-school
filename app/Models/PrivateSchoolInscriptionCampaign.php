<?php

namespace App\Models;

use App\Enums\PrivateSchoolCampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PrivateSchoolInscriptionCampaign extends Model
{
    use Concerns\GuardsAcademicYearWrites;

    protected $fillable = ['academic_year_id', 'title', 'description', 'deadline', 'status'];

    protected static function booted(): void
    {
        static::creating(fn (self $campaign) => $campaign->public_token ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['deadline' => 'datetime', 'status' => PrivateSchoolCampaignStatus::class];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(PrivateSchoolCampaignLevel::class, 'campaign_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(PrivateSchoolInscription::class, 'campaign_id');
    }

    public function acceptsRequests(): bool
    {
        return $this->status === PrivateSchoolCampaignStatus::OPEN
            && (! $this->deadline || $this->deadline->isFuture());
    }
}
