<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolCycle extends Model
{
    protected $fillable = ['name', 'code', 'sort_order', 'is_active'];
    protected function casts(): array { return ['sort_order' => 'integer', 'is_active' => 'boolean']; }
    public function levels(): HasMany { return $this->hasMany(SchoolLevel::class)->orderBy('sort_order'); }
}
