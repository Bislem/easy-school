<?php

namespace App\Models;

use App\Services\DefaultTenantRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;

class Role extends Model
{
    protected $fillable = ['name', 'description', 'system_key', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            if ($role->system_key && ! array_key_exists($role->system_key, DefaultTenantRoles::templates())) {
                throw ValidationException::withMessages([
                    'system_key' => 'Cette identité système de rôle n’est pas définie par la plateforme.',
                ]);
            }
        });
        static::updating(function (self $role): void {
            if ($role->isDirty('system_key')) {
                throw ValidationException::withMessages(['system_key' => "L'identité système d'un rôle ne peut pas être modifiée."]);
            }
            if ($role->isDirty('is_active') && ! $role->is_active) {
                app(\App\Services\TenantRbac::class)->assertCanDeactivateRole($role);
            }
        });
        static::deleting(function (self $role): void {
            if ($role->system_key) {
                throw ValidationException::withMessages(['role' => 'Un rôle système doit être conservé. Vous pouvez le renommer, modifier ses permissions ou le désactiver.']);
            }
            if ($role->users()->exists()) {
                throw ValidationException::withMessages(['role' => 'Ce rôle est utilisé. Désactivez-le plutôt que de le supprimer.']);
            }
        });
    }

    public function permissions(): BelongsToMany
    {
        $relation = $this->belongsToMany(Permission::class, 'role_permission')->withPivot('tenant_id')->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }

    public function users(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class, 'role_user')->withPivot('tenant_id', 'data_scope')->withTimestamps();
        $tenantId = app(\App\Tenancy\TenantContext::class)->id();

        return $tenantId ? $relation->withPivotValue('tenant_id', $tenantId) : $relation;
    }
}
