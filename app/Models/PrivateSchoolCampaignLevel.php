<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrivateSchoolCampaignLevel extends Model
{
    protected $fillable = ['campaign_id', 'school_level_id', 'max_places', 'is_open'];

    protected function casts(): array
    {
        return ['max_places' => 'integer', 'is_open' => 'boolean'];
    }

    protected static function booted(): void
    {
        $guard = function (self $campaignLevel): void {
            if ($campaignLevel->campaign_id && ! $campaignLevel->campaign()->firstOrFail()->academicYear->isWritable()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['academic_year' => 'Cette année scolaire est clôturée et ne peut plus être modifiée.']);
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PrivateSchoolInscriptionCampaign::class, 'campaign_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(SchoolLevel::class, 'school_level_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(PrivateSchoolInscription::class, 'campaign_level_id');
    }

    public function acceptedInscriptions(): HasMany
    {
        return $this->inscriptions()->where('status', 'accepted');
    }

    public function hasCapacity(): bool
    {
        return $this->max_places === null || $this->acceptedInscriptions()->count() < $this->max_places;
    }
}
