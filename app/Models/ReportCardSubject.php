<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardSubject extends Model
{
    protected $fillable = ['tenant_id', 'report_card_id', 'subject_id', 'teacher_id', 'subject_name', 'teacher_name', 'coefficient', 'include_in_general_average', 'is_informational', 'is_exempted', 'average', 'class_average', 'min_average', 'max_average', 'rank', 'appreciation'];

    protected function casts(): array
    {
        return ['coefficient' => 'decimal:2', 'include_in_general_average' => 'boolean', 'is_informational' => 'boolean', 'is_exempted' => 'boolean', 'average' => 'decimal:2', 'class_average' => 'decimal:2', 'min_average' => 'decimal:2', 'max_average' => 'decimal:2'];
    }

    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(SchoolSubject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
