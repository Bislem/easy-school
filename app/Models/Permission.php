<?php

namespace App\Models;

use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;

class Permission extends Model
{
    protected $fillable = ['key', 'name'];

    protected static function booted(): void
    {
        static::creating(function (self $permission): void {
            if (! array_key_exists($permission->key, PermissionCatalog::all())) {
                throw ValidationException::withMessages([
                    'key' => 'Cette permission n’est pas définie par la plateforme.',
                ]);
            }
        });

        static::updating(function (self $permission): void {
            if ($permission->isDirty('key')) {
                throw ValidationException::withMessages([
                    'key' => 'La clé stable d’une permission ne peut pas être modifiée.',
                ]);
            }
        });

        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'permission' => 'Les permissions sont définies centralement par la plateforme et ne peuvent pas être supprimées.',
            ]);
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission')->withPivot('tenant_id')->withTimestamps();
    }
}
