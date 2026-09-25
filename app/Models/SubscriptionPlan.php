<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['owner_tenant_id', 'name', 'slug', 'description', 'price', 'currency', 'billing_period', 'max_students', 'max_teachers', 'max_staff', 'max_sites', 'max_users', 'max_courses', 'storage_mb', 'features', 'is_custom', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'features' => 'array', 'is_custom' => 'boolean', 'is_active' => 'boolean'];
    }

    public function ownerTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'owner_tenant_id');
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
