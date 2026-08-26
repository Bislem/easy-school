<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SickLeaveEvent extends Model
{
    protected $fillable = ['actor_id', 'event', 'from_status', 'to_status', 'notes'];

    public function leave(): BelongsTo
    {
        return $this->belongsTo(SickLeave::class, 'sick_leave_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
