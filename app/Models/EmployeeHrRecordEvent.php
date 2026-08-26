<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHrRecordEvent extends Model
{
    protected $fillable = ['actor_id', 'event', 'from_status', 'to_status', 'notes'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(EmployeeHrRecord::class, 'employee_hr_record_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
