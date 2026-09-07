<?php
namespace App\Models\Concerns;
use App\Models\AcademicYear;
use Illuminate\Validation\ValidationException;
trait GuardsAcademicYearWrites
{
    protected static function bootGuardsAcademicYearWrites(): void
    {
        static::saving(function ($model): void { if ($model->academic_year_id && ! AcademicYear::findOrFail($model->academic_year_id)->isWritable()) throw ValidationException::withMessages(['academic_year' => 'Cette année scolaire est clôturée et ne peut plus être modifiée.']); });
        static::deleting(function ($model): void { if ($model->academic_year_id && ! AcademicYear::findOrFail($model->academic_year_id)->isWritable()) throw ValidationException::withMessages(['academic_year' => 'Les données historiques d’une année clôturée ne peuvent pas être supprimées.']); });
    }
}
