<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class SchoolSubject extends Course
{
    protected $table = 'courses';

    protected static function booted(): void
    {
        static::addGlobalScope('school_subject', fn (Builder $query) => $query->where('entity_type', 'subject'));
        static::creating(fn (SchoolSubject $subject) => $subject->entity_type = 'subject');
    }
}
