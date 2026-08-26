<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeHrRecord extends Model
{
    protected $fillable = ['staff_id', 'category', 'type', 'title', 'reference', 'starts_at', 'ends_at', 'status', 'score', 'amount', 'description', 'metadata', 'is_confidential', 'created_by', 'archived_at'];

    protected $casts = ['starts_at' => 'date:Y-m-d', 'ends_at' => 'date:Y-m-d', 'score' => 'decimal:2', 'amount' => 'decimal:2', 'metadata' => 'array', 'is_confidential' => 'boolean', 'archived_at' => 'datetime'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmployeeHrRecordEvent::class)->latest();
    }
}
