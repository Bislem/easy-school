<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_site_id',
        'name',
        'code',
        'capacity',
        'location',
        'description',
        'is_active',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(SchoolSite::class, 'school_site_id');
    }

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
