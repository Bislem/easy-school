<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\ReportCard;
use App\Models\ReportCardSubject;
use App\Models\SchoolLevel;
use Illuminate\Support\Collection;

final class ReportCardCalculationService
{
    public function calculateSubjectAverage(ReportCard $card, ReportCardSubject $subject): array
    {
        $period = $this->periodFor($card);
        if (! $period) {
            return ['average' => null, 'warnings' => [['code' => 'missing_period', 'message' => 'Période académique introuvable.']]];
        }
        $assessments = Assessment::where('tenant_id', $card->tenant_id)->where('academic_year_id', $card->academic_year_id)->where('academic_period_id', $period->id)->where('subject_id', $subject->subject_id)->whereIn('status', ['completed', 'locked'])->whereHas('groups', fn ($q) => $q->whereKey($card->school_group_id))->get();
        $grades = Grade::whereIn('assessment_id', $assessments->pluck('id'))->where('student_academic_enrollment_id', $card->student_academic_enrollment_id)->get();
        $result = app(AcademicGradeService::class)->subjectResult($assessments, $grades);

        return ['average' => $result['average'], 'warnings' => $result['average'] === null ? [['code' => 'no_grades', 'message' => 'Aucune note valide pour cette matière.']] : []];
    }

    public function recalculateReportCard(ReportCard $card): array
    {
        if (in_array($card->status, ['validated', 'published', 'locked'], true)) {
            return ['report_card' => $card->fresh('subjects'), 'warnings' => [], 'errors' => [['code' => 'immutable', 'message' => 'Ce bulletin est verrouillé pour recalcul automatique.']]];
        }
        $card->loadMissing('subjects');
        $warnings = [];
        $eligible = [];
        $coefficients = SchoolLevel::findOrFail($card->school_level_id)->subjects()->wherePivot('is_active', true)->pluck('coefficient', 'courses.id');
        foreach ($card->subjects as $subject) {
            $isAssigned = $coefficients->has($subject->subject_id);
            if ($isAssigned) {
                $subject->update(['coefficient' => $coefficients[$subject->subject_id]]);
            } else {
                $warnings[] = ['code' => 'subject_not_assigned', 'subject_id' => $subject->subject_id, 'message' => 'Cette matière n’est plus affectée au niveau et a été exclue de la moyenne générale.'];
            }
            if ((float) $subject->coefficient <= 0) {
                $warnings[] = ['code' => 'missing_coefficient', 'subject_id' => $subject->subject_id, 'message' => 'La matière n’a pas de coefficient valide.'];
            }
            if (! $subject->teacher_id) {
                $warnings[] = ['code' => 'missing_teacher', 'subject_id' => $subject->subject_id, 'message' => 'Aucun enseignant affecté.'];
            }
            $result = $this->calculateSubjectAverage($card, $subject);
            $warnings = array_merge($warnings, $result['warnings']);
            $subject->update(['average' => $result['average']]);
            if ($isAssigned && $result['average'] !== null && $subject->include_in_general_average && ! $subject->is_informational && ! $subject->is_exempted) {
                $eligible[] = $subject;
            }
        }
        $coefficientTotal = collect($eligible)->sum(fn ($s) => (float) $s->coefficient);
        $general = $coefficientTotal > 0 ? round(collect($eligible)->sum(fn ($s) => (float) $s->average * (float) $s->coefficient) / $coefficientTotal, 2) : null;
        $card->update(['general_average' => $general, 'source_data_changed' => false]);
        $this->calculateClassStatistics($card);

        return ['report_card' => $card->fresh('subjects'), 'warnings' => $warnings, 'errors' => []];
    }

    public function calculateClassStatistics(ReportCard $card): void
    {
        $cards = ReportCard::where('tenant_id', $card->tenant_id)->where('academic_year_id', $card->academic_year_id)->where('school_group_id', $card->school_group_id)->where('period_key', $card->period_key)->with('subjects')->get();
        foreach ($card->subjects as $subject) {
            $values = $cards->flatMap(fn ($c) => $c->subjects->where('subject_id', $subject->subject_id)->pluck('average'))->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->sort()->values();
            $this->persistSubjectStats($subject, $values);
            $this->persistSubjectRank($card, $subject, $cards);
        }
        $averages = $cards->filter(fn ($c) => $c->general_average !== null)->sortByDesc('general_average')->values();
        $card->update(['class_average' => $averages->count() ? round($averages->avg(fn ($item) => (float) $item->general_average), 2) : null]);
        $this->persistRank($card, $averages, 'general_average');
    }

    public function calculateGroupReportCards(int $tenantId, int $groupId, string $period): array
    {
        $cards = ReportCard::where('tenant_id', $tenantId)->where('school_group_id', $groupId)->where('period_key', $period)->where('status', 'draft')->get();
        $results = $cards->map(fn (ReportCard $card) => $period === 'annual' ? $this->calculateAnnualReport($card) : $this->recalculateReportCard($card))->all();
        $cards->each(fn (ReportCard $card) => $this->calculateClassStatistics($card->fresh('subjects')));

        return $results;
    }

    public function calculateAnnualReport(ReportCard $annual): array
    {
        $weights = config('report_cards.annual_weights');
        $values = [];
        foreach ($weights as $period => $weight) {
            $v = ReportCard::where('tenant_id', $annual->tenant_id)->where('student_academic_enrollment_id', $annual->student_academic_enrollment_id)->where('period_key', $period)->value('general_average');
            if ($v !== null) {
                $values[] = [(float) $v, (float) $weight];
            }
        }
        $average = collect($values)->sum(fn ($v) => $v[0] * $v[1]) / max(collect($values)->sum(fn ($v) => $v[1]), 1);
        if ($annual->status === 'draft') {
            $annual->update(['general_average' => count($values) ? round($average, 2) : null, 'source_data_changed' => false]);
        }

        return ['report_card' => $annual->fresh(), 'warnings' => count($values) < 3 ? [['code' => 'incomplete_year', 'message' => 'Une ou plusieurs périodes trimestrielles sont incomplètes.']] : [], 'errors' => []];
    }

    private function periodFor(ReportCard $card): ?AcademicPeriod
    {
        $number = ['trimester_1' => 1, 'trimester_2' => 2, 'trimester_3' => 3][$card->period_key] ?? null;

        return $number ? AcademicPeriod::where('academic_year_id', $card->academic_year_id)->where('number', $number)->first() : null;
    }

    private function persistSubjectStats(ReportCardSubject $subject, Collection $values): void
    {
        $subject->update(['class_average' => $values->count() ? round($values->avg(), 2) : null, 'min_average' => $values->min(), 'max_average' => $values->max()]);
    }

    private function persistSubjectRank(ReportCard $card, ReportCardSubject $subject, Collection $cards): void
    {
        $rows = $cards->flatMap(fn ($c) => $c->subjects->where('subject_id', $subject->subject_id)->filter(fn ($s) => $s->average !== null))->sortByDesc('average')->values();
        $rank = 0;
        $last = null;
        foreach ($rows as $i => $row) {
            if ($last === null || (float) $row->average !== $last) {
                $rank = $i + 1;
            } if ($row->report_card_id === $card->id) {
                $subject->update(['rank' => $rank]);
            } $last = (float) $row->average;
        }
    }

    private function persistRank(ReportCard $card, Collection $cards, string $field): void
    {
        $rank = 0;
        $last = null;
        foreach ($cards as $i => $item) {
            if ($last === null || (float) $item->{$field} !== $last) {
                $rank = $i + 1;
            } if ($item->id === $card->id) {
                $card->update(['rank' => $rank, 'total_students' => $cards->count()]);
            } $last = (float) $item->{$field};
        }
    }
}
