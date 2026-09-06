<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoRequest extends Model
{
    protected $fillable = ['school_name', 'school_type', 'contact_name', 'contact_role', 'email', 'phone', 'address', 'wilaya', 'commune', 'website', 'students_count', 'teachers_count', 'staff_count', 'sites_count', 'requested_days', 'needs', 'modules', 'status', 'rejection_reason', 'reviewed_by', 'reviewed_at', 'tenant_id', 'credentials_sent_at'];

    protected function casts(): array
    {
        return ['modules' => 'array', 'reviewed_at' => 'datetime', 'credentials_sent_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withoutGlobalScopes();
    }
}
