<?php

namespace App\Services;

use App\Enums\StudentAcademicEnrollmentStatus;
use App\Models\FinancialAccount;
use App\Models\SchoolFeeStructure;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class SchoolFeeGenerationService
{
    /** The most specific active structure wins: student > group > level > whole year. */
    public function applicableStructure(StudentAcademicEnrollment $enrollment): ?SchoolFeeStructure
    {
        return SchoolFeeStructure::with(['scheduleItems'])->where('academic_year_id', $enrollment->academic_year_id)->where('is_active', true)
            ->where(fn (Builder $q) => $q->where('student_id', $enrollment->student_id)
                ->orWhere(fn (Builder $q) => $q->whereNull('student_id')->where('school_group_id', $enrollment->school_group_id))
                ->orWhere(fn (Builder $q) => $q->whereNull('student_id')->whereNull('school_group_id')->where('school_level_id', $enrollment->school_level_id))
                ->orWhere(fn (Builder $q) => $q->whereNull('student_id')->whereNull('school_group_id')->whereNull('school_level_id')))
            ->get()->sortByDesc(fn (SchoolFeeStructure $s) => [$s->specificity(), $s->id])->first();
    }

    public function targetQuery(SchoolFeeStructure $structure, ?int $levelId = null, ?int $groupId = null): Builder
    {
        return StudentAcademicEnrollment::query()->where('academic_year_id', $structure->academic_year_id)->where('status', StudentAcademicEnrollmentStatus::ENROLLED)
            ->when($structure->student_id, fn ($q, $id) => $q->where('student_id', $id))
            ->when($structure->school_group_id, fn ($q, $id) => $q->where('school_group_id', $id))
            ->when($structure->school_level_id, fn ($q, $id) => $q->where('school_level_id', $id))
            ->when($levelId, fn ($q) => $q->where('school_level_id', $levelId))
            ->when($groupId, fn ($q) => $q->where('school_group_id', $groupId));
    }

    public function preview(SchoolFeeStructure $structure, ?int $levelId, ?int $groupId): array
    {
        $query = $this->targetQuery($structure, $levelId, $groupId);
        $total = (clone $query)->count();
        $existing = $this->accounts()->where('domain', 'school')->where('accountable_type', (new StudentAcademicEnrollment)->getMorphClass())->whereIn('accountable_id', (clone $query)->select('id'))->count();

        return ['students' => $total, 'existing' => $existing, 'new' => $total - $existing];
    }

    public function generate(SchoolFeeStructure $structure, ?int $levelId, ?int $groupId, bool $updateExisting = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $structure->loadMissing('scheduleItems');
        $this->targetQuery($structure, $levelId, $groupId)->chunkById(100, function ($enrollments) use ($structure, $updateExisting, &$result) {
            foreach ($enrollments as $enrollment) {
                $result[$this->generateForEnrollment($enrollment, $structure, $updateExisting)]++;
            }
        });

        return $result;
    }

    /** @return 'created'|'updated'|'skipped' */
    public function generateForEnrollment(StudentAcademicEnrollment $enrollment, ?SchoolFeeStructure $structure = null, bool $updateExisting = false): string
    {
        $structure ??= $this->applicableStructure($enrollment);
        if (! $structure || $enrollment->status !== StudentAcademicEnrollmentStatus::ENROLLED) {
            return 'skipped';
        }

        return DB::transaction(function () use ($enrollment, $structure, $updateExisting) {
            $account = $this->accounts()->firstOrCreate(['accountable_type' => $enrollment->getMorphClass(), 'accountable_id' => $enrollment->id], ['domain' => 'school', 'student_id' => $enrollment->student_id, 'academic_year_id' => $enrollment->academic_year_id, 'school_fee_structure_id' => $structure->id]);
            if (! $account->wasRecentlyCreated) {
                $current = $account->feeStructure;
                $moreSpecific = $current && $current->id !== $structure->id && $current->specificity() > $structure->specificity();
                if (! $updateExisting || $account->closed_at || $moreSpecific || $account->transactions()->withoutGlobalScope(AuthorizationService::DATA_SCOPE)->exists()) {
                    return 'skipped';
                }
            }
            $account->update(['school_fee_structure_id' => $structure->id, 'student_id' => $enrollment->student_id, 'academic_year_id' => $enrollment->academic_year_id]);
            $keys = [];
            foreach ($structure->scheduleItems as $item) {
                $key = 'schedule-'.$item->id;
                $keys[] = $key;
                $account->installments()->updateOrCreate(['source_key' => $key], ['school_fee_component_id' => $item->school_fee_component_id, 'label' => $item->label, 'amount' => $item->amount, 'due_date' => $item->due_date, 'sort_order' => $item->sort_order]);
            }
            $account->installments()->whereNotIn('source_key', $keys)->whereDoesntHave('allocations')->delete();
            app(FinancialAccountService::class)->refresh($account);

            return $account->wasRecentlyCreated ? 'created' : 'updated';
        });
    }

    private function accounts(): Builder
    {
        return FinancialAccount::withoutGlobalScope(AuthorizationService::DATA_SCOPE);
    }
}
