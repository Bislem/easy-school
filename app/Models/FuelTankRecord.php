<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelTankRecord extends Model
{
    public const AT_RENTAL_START = 'rental_start';
    public const AT_RENTAL_END = 'rental_end';

    protected $fillable = [
        'car_id',
        'reservation_id',
        'recorded_by',
        'record_type',
        'fuel_level',
        'notes',
        'recorded_at',
    ];

    protected $casts = [
        'fuel_level' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
