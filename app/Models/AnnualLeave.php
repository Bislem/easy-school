<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualLeave extends Model
{
    protected $fillable = ['staff_id', 'mode', 'starts_at', 'ends_at', 'days', 'status', 'reason', 'notes', 'requested_at', 'created_by', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'rejection_reason', 'cancelled_at', 'cancelled_by', 'actual_return_date'];

    protected $casts = ['starts_at' => 'date:Y-m-d', 'ends_at' => 'date:Y-m-d', 'actual_return_date' => 'date:Y-m-d', 'days' => 'decimal:2', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];

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
        return $this->hasMany(AnnualLeaveEvent::class)->latest();
    }
}
