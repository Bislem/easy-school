<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileMembership extends Model
{
    protected $fillable = ['user_id', 'tenant_id', 'role', 'parent_id', 'is_active'];

    protected function casts(): array
    {
        return ['role' => UserRole::class, 'is_active' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function schoolParent(): BelongsTo { return $this->belongsTo(SchoolParent::class, 'parent_id'); }
}
