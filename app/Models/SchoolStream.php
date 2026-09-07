<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SchoolStream extends Model
{
    protected $fillable = ['code', 'name_fr', 'name_ar', 'curriculum_code', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_school_level')->withPivot(['school_level_id', 'curriculum_code', 'is_optional', 'is_active', 'display_order', 'choice_group'])->withTimestamps();
    }
}
