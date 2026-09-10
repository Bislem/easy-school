<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'logo', 'phone', 'email', 'address', 'wilaya', 'commune', 'status', 'account_type', 'organization_type', 'demo_expires_at', 'subscription_plan_id', 'payment_proof_path', 'registration_submitted_at', 'registration_reviewed_at', 'registration_rejection_reason', 'plan_started_at', 'plan_expires_at', 'settings', 'storage_used_bytes', 'storage_limit_bytes'];

    protected $appends = ['logo_url'];

    protected $hidden = ['settings'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'demo_expires_at' => 'datetime', 'registration_submitted_at' => 'datetime', 'registration_reviewed_at' => 'datetime', 'plan_started_at' => 'datetime', 'plan_expires_at' => 'datetime', 'storage_used_bytes' => 'integer', 'storage_limit_bytes' => 'integer'];
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function isDemo(): bool
    {
        return $this->account_type === 'demo';
    }

    public function demoExpired(): bool
    {
        return $this->isDemo() && (! $this->demo_expires_at || $this->demo_expires_at->isPast());
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function mobileMemberships(): HasMany
    {
        return $this->hasMany(MobileMembership::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function storedFiles(): HasMany
    {
        return $this->hasMany(TenantStoredFile::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }
}
