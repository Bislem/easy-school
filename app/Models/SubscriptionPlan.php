<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'price', 'currency', 'billing_period', 'max_students', 'max_teachers', 'max_staff', 'max_sites', 'max_users', 'max_courses', 'storage_mb', 'features', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'features' => 'array', 'is_active' => 'boolean'];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
