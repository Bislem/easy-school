<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Formation extends Course
{
    protected $table = 'courses';

    protected static function booted(): void
    {
        static::addGlobalScope('formation', fn (Builder $query) => $query->where('entity_type', 'formation'));
        static::creating(fn (Formation $formation) => $formation->entity_type = 'formation');
    }
}
