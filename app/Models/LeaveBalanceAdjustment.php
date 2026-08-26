<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceAdjustment extends Model
{
    protected $fillable = ['previous_balance', 'previous_as_of', 'new_balance', 'new_as_of', 'reason', 'created_by'];

    protected $casts = ['previous_balance' => 'decimal:2', 'new_balance' => 'decimal:2', 'previous_as_of' => 'date:Y-m-d', 'new_as_of' => 'date:Y-m-d'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
