<?php

namespace App\Models;

use App\Enums\RoomType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_site_id',
        'name',
        'code',
        'type',
        'capacity',
        'location',
        'description',
        'is_active',
        'is_available',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(SchoolSite::class, 'school_site_id');
    }

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'type' => RoomType::class,
            'is_active' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function timetableSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TimetableSession::class);
    }

    public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RoomReservation::class);
    }
}
