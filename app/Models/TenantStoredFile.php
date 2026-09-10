<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantStoredFile extends Model
{
    protected $fillable = ['tenant_id', 'disk', 'storage_key', 'original_filename', 'size_bytes', 'mime_type', 'category', 'module', 'related_entity_type', 'related_entity_id'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
