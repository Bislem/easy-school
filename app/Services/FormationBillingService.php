<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\CourseEnrollment;
use App\Models\FinancialAccount;
use App\Models\FormationPricingConfig;
use Illuminate\Support\Facades\DB;

final class FormationBillingService
{
    public function applicablePricing(CourseEnrollment $enrollment): ?FormationPricingConfig
    {
        $enrollment->loadMissing('form:id,course_id,start_date', 'trainingPlanGroup:id');
        if (! $enrollment->form?->course_id) {
            return null;
        }

        return FormationPricingConfig::with('scheduleItems')->where('course_id', $enrollment->form->course_id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('enrollment_form_id')->orWhere('enrollment_form_id', $enrollment->enrollment_form_id))
            ->where(fn ($q) => $q->whereNull('training_plan_group_id')->orWhere('training_plan_group_id', $enrollment->training_plan_group_id))
            ->orderByRaw('training_plan_group_id is not null desc')->orderByRaw('enrollment_form_id is not null desc')->latest('id')->first();
    }

    public function generateForEnrollment(CourseEnrollment $enrollment, ?FormationPricingConfig $pricing = null, bool $updateExisting = false): array
    {
        if (! $enrollment->student_id || $enrollment->status !== ApplicationStatus::REGISTERED) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 1];
        }
        $pricing ??= $this->applicablePricing($enrollment);
        if (! $pricing) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 1];
        }

        return DB::transaction(function () use ($enrollment, $pricing, $updateExisting) {
            $account = FinancialAccount::firstOrCreate(['accountable_type' => $enrollment->getMorphClass(), 'accountable_id' => $enrollment->id], ['domain' => 'formation', 'student_id' => $enrollment->student_id]);
            if (! $account->wasRecentlyCreated && (! $updateExisting || $account->transactions()->exists())) {
                return ['created' => 0, 'updated' => 0, 'skipped' => 1];
            }
            $account->update(['domain' => 'formation', 'student_id' => $enrollment->student_id]);
            $items = $pricing->scheduleItems;
            if ($items->isEmpty()) {
                $items = collect([(object) ['id' => 'full', 'label' => 'Paiement intégral', 'amount' => $pricing->total_price, 'due_date' => $enrollment->form?->start_date ?? today(), 'sort_order' => 0]]);
            }
            $keys = [];
            foreach ($items as $item) {
                $key = 'formation-pricing-'.$pricing->id.'-'.$item->id;
                $keys[] = $key;
                $account->installments()->updateOrCreate(['source_key' => $key], ['label' => $item->label, 'amount' => $item->amount, 'due_date' => $item->due_date, 'sort_order' => $item->sort_order]);
            }
            $account->installments()->whereNotIn('source_key', $keys)->whereDoesntHave('allocations')->delete();
            app(FinancialAccountService::class)->refresh($account);

            return ['created' => $account->wasRecentlyCreated ? 1 : 0, 'updated' => $account->wasRecentlyCreated ? 0 : 1, 'skipped' => 0];
        });
    }

    public function targetQuery(?int $courseId, ?int $sessionId, ?int $groupId)
    {
        return CourseEnrollment::query()->whereNotNull('student_id')->where('status', 'registered')->when($courseId, fn ($q) => $q->whereHas('form', fn ($f) => $f->where('course_id', $courseId)))->when($sessionId, fn ($q) => $q->where('enrollment_form_id', $sessionId))->when($groupId, fn ($q) => $q->where('training_plan_group_id', $groupId));
    }

    public function preview(?int $courseId, ?int $sessionId, ?int $groupId): array
    {
        $query = $this->targetQuery($courseId, $sessionId, $groupId);
        $total = (clone $query)->count();
        $existing = FinancialAccount::where('domain', 'formation')->where('accountable_type', (new CourseEnrollment)->getMorphClass())->whereIn('accountable_id', (clone $query)->select('id'))->count();

        return ['enrollments' => $total, 'existing' => $existing, 'new' => $total - $existing];
    }

    public function generate(FormationPricingConfig $pricing, ?int $sessionId, ?int $groupId, bool $updateExisting = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $this->targetQuery($pricing->course_id, $sessionId, $groupId)->with(['form', 'trainingPlanGroup'])->chunkById(100, function ($rows) use ($pricing, $updateExisting, &$result) {
            foreach ($rows as $enrollment) {
                $row = $this->generateForEnrollment($enrollment, $pricing, $updateExisting);
                foreach ($result as $key => $value) {
                    $result[$key] += $row[$key];
                }
            }
        });

        return $result;
    }
}
