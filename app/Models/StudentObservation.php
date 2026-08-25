<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentObservation extends Model
{
    protected $fillable = ['student_id', 'author_id', 'parent_id', 'message'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id')->oldest(); }
}
