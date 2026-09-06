<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherUnavailablePeriod extends Model
{
    protected $fillable = ['teacher_id', 'starts_at', 'ends_at', 'reason'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
}
