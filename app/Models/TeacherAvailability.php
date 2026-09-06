<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAvailability extends Model
{
    protected $fillable = ['teacher_id', 'day', 'start_time', 'end_time', 'is_available'];
    protected function casts(): array { return ['day' => 'integer', 'is_available' => 'boolean']; }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
}
