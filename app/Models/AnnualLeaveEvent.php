<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnualLeaveEvent extends Model
{
    protected $fillable = ['actor_id', 'event', 'from_status', 'to_status', 'notes'];

    public function leave(): BelongsTo
    {
        return $this->belongsTo(AnnualLeave::class, 'annual_leave_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
