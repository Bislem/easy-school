<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'code',
        'category',
        'duration_hours',
        'price',
        'description',
        'objectives',
        'prerequisites',
        'is_certified',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_hours' => 'integer',
            'price' => 'decimal:2',
            'is_certified' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function levels(): HasMany { return $this->hasMany(CourseLevel::class); }
}
