<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SickLeave extends Model
{
    protected $fillable = ['staff_id', 'category', 'starts_at', 'ends_at', 'days', 'status', 'certificate_received', 'certificate_reference', 'certificate_issued_at', 'health_professional', 'administrative_notes', 'requested_at', 'created_by', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason', 'cancelled_at', 'cancelled_by', 'actual_return_date', 'fit_to_return_confirmed'];

    protected $casts = ['starts_at' => 'date:Y-m-d', 'ends_at' => 'date:Y-m-d', 'certificate_issued_at' => 'date:Y-m-d', 'actual_return_date' => 'date:Y-m-d', 'certificate_received' => 'boolean', 'fit_to_return_confirmed' => 'boolean', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SickLeaveEvent::class)->latest();
    }
}
