<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Enums\UserRole;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'birth_date',
        'driving_license_number',
        'driving_license_delivered_at',
        'driving_license_authority',
        'driving_license_path',
        'approval_status',
        'rejection_reason',
        'approved_at',
        'approved_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'birth_date' => 'date:Y-m-d',
            'driving_license_delivered_at' => 'date:Y-m-d',
            'approved_at' => 'datetime',
        ];
    }

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

    /**
     * Get the reservations for the user.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    /**
     * Get the payments for the user.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
