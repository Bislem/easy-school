<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\SchoolFeeStructure;
use App\Models\StudentAcademicEnrollment;
use Illuminate\Support\Facades\DB;

final class SchoolFeeGenerationService
{
    public function preview(int $yearId, ?int $levelId, ?int $groupId): array
    {
        $query = StudentAcademicEnrollment::where('academic_year_id', $yearId)->where('status', 'enrolled')->when($levelId, fn ($q) => $q->where('school_level_id', $levelId))->when($groupId, fn ($q) => $q->where('school_group_id', $groupId));
        $total = (clone $query)->count();
        $existing = FinancialAccount::where('domain', 'school')->whereIn('accountable_id', (clone $query)->select('id'))->where('accountable_type', (new StudentAcademicEnrollment)->getMorphClass())->count();

        return ['students' => $total, 'existing' => $existing, 'new' => $total - $existing];
    }

    public function generate(SchoolFeeStructure $structure, int $yearId, ?int $levelId, ?int $groupId, bool $updateExisting = false, array $optionalComponentIds = []): array
    {
        $enrollments = StudentAcademicEnrollment::where('academic_year_id', $yearId)->where('status', 'enrolled')->when($levelId, fn ($q) => $q->where('school_level_id', $levelId))->when($groupId, fn ($q) => $q->where('school_group_id', $groupId))->get();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        DB::transaction(function () use ($enrollments, $structure, $updateExisting, $optionalComponentIds, &$created, &$updated, &$skipped) {
            $allowedComponents = $structure->components->filter(fn ($c) => $c->is_mandatory || in_array($c->id, $optionalComponentIds, true))->pluck('id');
            $schedule = $structure->scheduleItems->filter(fn ($item) => ! $item->school_fee_component_id || $allowedComponents->contains($item->school_fee_component_id));
            foreach ($enrollments as $enrollment) {
                $account = FinancialAccount::firstOrCreate(['accountable_type' => $enrollment->getMorphClass(), 'accountable_id' => $enrollment->id], ['domain' => 'school', 'student_id' => $enrollment->student_id, 'academic_year_id' => $enrollment->academic_year_id, 'school_fee_structure_id' => $structure->id]);
                if (! $account->wasRecentlyCreated && (! $updateExisting || $account->transactions()->exists())) {
                    $skipped++;

                    continue;
                }
                $account->update(['school_fee_structure_id' => $structure->id, 'student_id' => $enrollment->student_id, 'academic_year_id' => $enrollment->academic_year_id]);
                $keys = [];
                foreach ($schedule as $item) {
                    $key = 'schedule-'.$item->id;
                    $keys[] = $key;
                    $account->installments()->updateOrCreate(['source_key' => $key], ['school_fee_component_id' => $item->school_fee_component_id, 'label' => $item->label, 'amount' => $item->amount, 'due_date' => $item->due_date, 'sort_order' => $item->sort_order]);
                }
                $account->installments()->whereNotIn('source_key', $keys)->whereDoesntHave('allocations')->delete();
                app(FinancialAccountService::class)->refresh($account);
                $account->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return compact('created','updated','skipped');
    }
}
