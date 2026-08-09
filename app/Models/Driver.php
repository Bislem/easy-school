<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'full_name', 'phone', 'email', 'driving_license_path',
        'approval_status', 'rejection_reason', 'approved_at', 'approved_by',
    ];

    protected $casts = ['approved_at' => 'datetime'];

    protected $appends = ['driving_license_url'];

    public function getDrivingLicenseUrlAttribute(): ?string
    {
        if (!$this->driving_license_path) {
            return null;
        }

        $host = app()->runningInConsole()
            ? config('app.url')
            : request()->getSchemeAndHttpHost();

        return rtrim($host, '/') . '/storage/' . ltrim($this->driving_license_path, '/');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'secondary_driver_id');
    }

    public function requestedReservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'requested_driver_id');
    }
}
