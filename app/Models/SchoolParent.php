<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SchoolParent extends Model
{
    protected $table = 'parents';

    protected $fillable = ['user_id', 'first_name', 'last_name', 'phone', 'relationship'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope('tenant');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')->withPivot('is_primary', 'is_visible')->withTimestamps();
    }

    public function mobileMembership(): HasOne
    {
        return $this->hasOne(MobileMembership::class, 'parent_id');
    }
}
