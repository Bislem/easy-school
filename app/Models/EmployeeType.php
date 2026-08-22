<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeType extends Model
{
    protected $fillable = ['name', 'slug', 'is_teacher', 'is_active', 'sort_order'];

    protected $casts = ['is_teacher' => 'boolean', 'is_active' => 'boolean'];

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
