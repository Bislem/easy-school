<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MohamedGaldi\ViltFilepond\Models\File;

class EmployeeDocument extends Model
{
    protected $fillable = ['staff_id', 'file_id', 'type', 'title', 'reference', 'issued_at', 'expires_at', 'notes', 'uploaded_by'];

    protected $casts = ['issued_at' => 'date:Y-m-d', 'expires_at' => 'date:Y-m-d'];

    protected $appends = ['type_label', 'expiry_status'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return config('hr.document_types.'.$this->type, $this->type);
    }

    public function getExpiryStatusAttribute(): ?string
    {
        if (! $this->expires_at) {
            return null;
        }
        if ($this->expires_at->isPast()) {
            return 'expired';
        }
        if ($this->expires_at->lte(today()->addDays(30))) {
            return 'expiring';
        }

        return 'valid';
    }
}
