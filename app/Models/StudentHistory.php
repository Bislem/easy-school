<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentHistory extends Model
{
    protected $fillable = ['student_id', 'user_id', 'event', 'from_status', 'to_status', 'description', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
