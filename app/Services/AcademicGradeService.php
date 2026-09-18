<?php

namespace App\Services;

use Illuminate\Support\Collection;

/** Central calculation policy for assessment grades. All averages are normalized to /20. */
final class AcademicGradeService
{
    /**
     * @param Collection<int, \App\Models\Assessment> $assessments
     * @param Collection<int, \App\Models\Grade> $grades
     * @return array{average:?float,completed:int,total:int,missing:int,has_absence:bool,has_exemption:bool}
     */
    public function subjectResult(Collection $assessments, Collection $grades): array
    {
        $byAssessment = $grades->keyBy('assessment_id');
        $weightedTotal = 0.0;
        $totalWeight = 0.0;
        $completed = 0;
        $hasAbsence = false;
        $hasExemption = false;

        foreach ($assessments as $assessment) {
            $grade = $byAssessment->get($assessment->id);
            if (! $grade || $grade->status === 'not_graded') {
                continue;
            }

            $completed++;
            if ($grade->status === 'absent') {
                $hasAbsence = true;
                continue;
            }
            if ($grade->status === 'exempted') {
                $hasExemption = true;
                continue;
            }

            $value = $grade->status === 'absent_with_zero' ? 0.0 : (float) $grade->value;
            $weight = (float) $assessment->weight;
            $normalized = ((float) $assessment->maximum_grade) > 0
                ? ($value / (float) $assessment->maximum_grade) * 20
                : null;

            if ($normalized !== null && $weight > 0) {
                $weightedTotal += $normalized * $weight;
                $totalWeight += $weight;
            }
        }

        $total = $assessments->count();
        return [
            'average' => $totalWeight > 0 ? round($weightedTotal / $totalWeight, 2) : null,
            'completed' => $completed,
            'total' => $total,
            'missing' => max($total - $completed, 0),
            'has_absence' => $hasAbsence,
            'has_exemption' => $hasExemption,
        ];
    }
}
