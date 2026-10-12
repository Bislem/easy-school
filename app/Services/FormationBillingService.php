<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\CourseEnrollment;
use App\Models\FinancialAccount;
use App\Models\FormationPricingConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class FormationBillingService
{
    /** A formation enrollment belongs to a course through its session or its training-plan group level. */
    public function courseId(CourseEnrollment $enrollment): ?int
    {
        $enrollment->loadMissing('form:id,course_id,start_date', 'trainingPlanGroup.plan.level:id,course_id,price');

        return $enrollment->form?->course_id ?? $enrollment->trainingPlanGroup?->plan?->level?->course_id;
    }

    /** The most specific active pricing wins: group > session > formation. */
    public function applicablePricing(CourseEnrollment $enrollment): ?FormationPricingConfig
    {
        $courseId = $this->courseId($enrollment);
        if (! $courseId) {
            return null;
        }

        return FormationPricingConfig::with('scheduleItems')->where('course_id', $courseId)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('enrollment_form_id')->orWhere('enrollment_form_id', $enrollment->enrollment_form_id))
            ->where(fn ($q) => $q->whereNull('training_plan_group_id')->orWhere('training_plan_group_id', $enrollment->training_plan_group_id))
            ->orderByRaw('training_plan_group_id is not null desc')->orderByRaw('enrollment_form_id is not null desc')->latest('id')->first();
    }

    /** @return array{created:int, updated:int, skipped:int} */
    public function generateForEnrollment(CourseEnrollment $enrollment, ?FormationPricingConfig $pricing = null, bool $updateExisting = false): array
    {
        $skipped = ['created' => 0, 'updated' => 0, 'skipped' => 1];
        if (! $enrollment->student_id || $enrollment->status !== ApplicationStatus::REGISTERED) {
            return $skipped;
        }
        $pricing ??= $this->applicablePricing($enrollment);
        $items = $pricing ? $this->pricingItems($pricing, $enrollment) : $this->defaultItems($enrollment);
        if ($items === []) {
            return $skipped;
        }

        return DB::transaction(function () use ($enrollment, $items, $updateExisting, $skipped) {
            $account = $this->accounts()->firstOrCreate(['accountable_type' => $enrollment->getMorphClass(), 'accountable_id' => $enrollment->id], ['domain' => 'formation', 'student_id' => $enrollment->student_id]);
            if (! $account->wasRecentlyCreated && (! $updateExisting || $account->closed_at || $account->transactions()->withoutGlobalScope(AuthorizationService::DATA_SCOPE)->exists())) {
                return $skipped;
            }
            $account->update(['domain' => 'formation', 'student_id' => $enrollment->student_id]);
            foreach ($items as $key => $item) {
                $account->installments()->updateOrCreate(['source_key' => $key], $item);
            }
            $account->installments()->whereNotIn('source_key', array_keys($items))->whereDoesntHave('allocations')->delete();
            app(FinancialAccountService::class)->refresh($account);

            return ['created' => $account->wasRecentlyCreated ? 1 : 0, 'updated' => $account->wasRecentlyCreated ? 0 : 1, 'skipped' => 0];
        });
    }

    public function targetQuery(?int $courseId, ?int $sessionId, ?int $groupId): Builder
    {
        return CourseEnrollment::query()->whereNotNull('student_id')->where('status', ApplicationStatus::REGISTERED)
            ->when($courseId, fn ($q) => $q->where(fn ($q) => $q->whereHas('form', fn ($f) => $f->where('course_id', $courseId))
                ->orWhereHas('trainingPlanGroup.plan.level', fn ($l) => $l->where('course_id', $courseId))))
            ->when($sessionId, fn ($q) => $q->where('enrollment_form_id', $sessionId))
            ->when($groupId, fn ($q) => $q->where('training_plan_group_id', $groupId));
    }

    public function preview(?int $courseId, ?int $sessionId, ?int $groupId): array
    {
        $query = $this->targetQuery($courseId, $sessionId, $groupId);
        $total = (clone $query)->count();
        $existing = $this->accounts()->where('domain', 'formation')->where('accountable_type', (new CourseEnrollment)->getMorphClass())->whereIn('accountable_id', (clone $query)->select('id'))->count();

        return ['enrollments' => $total, 'existing' => $existing, 'new' => $total - $existing];
    }

    public function generate(FormationPricingConfig $pricing, ?int $sessionId, ?int $groupId, bool $updateExisting = false): array
    {
        $pricing->loadMissing('scheduleItems');
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $this->targetQuery($pricing->course_id, $sessionId ?? $pricing->enrollment_form_id, $groupId ?? $pricing->training_plan_group_id)
            ->with(['form', 'trainingPlanGroup.plan.level'])->chunkById(100, function ($rows) use ($pricing, $updateExisting, &$result) {
                foreach ($rows as $enrollment) {
                    foreach ($this->generateForEnrollment($enrollment, $pricing, $updateExisting) as $key => $value) {
                        $result[$key] += $value;
                    }
                }
            });

        return $result;
    }

    /** @return array<string, array<string, mixed>> */
    private function pricingItems(FormationPricingConfig $pricing, CourseEnrollment $enrollment): array
    {
        if ($pricing->scheduleItems->isEmpty()) {
            return ['formation-pricing-'.$pricing->id.'-full' => ['label' => 'Paiement intégral', 'amount' => $pricing->total_price, 'due_date' => $this->defaultDueDate($enrollment), 'sort_order' => 0]];
        }

        return $pricing->scheduleItems->mapWithKeys(fn ($item) => ['formation-pricing-'.$pricing->id.'-'.$item->id => ['label' => $item->label, 'amount' => $item->amount, 'due_date' => $item->due_date, 'sort_order' => $item->sort_order]])->all();
    }

    /** Without a pricing configuration, bill the level price (training plan) or the formation catalogue price. */
    private function defaultItems(CourseEnrollment $enrollment): array
    {
        $enrollment->loadMissing('form.course:id,price');
        $price = (float) ($enrollment->trainingPlanGroup?->plan?->level?->price ?: $enrollment->form?->course?->price ?: 0);

        return $price > 0 ? ['formation-default-price' => ['label' => 'Prix de la formation', 'amount' => $price, 'due_date' => $this->defaultDueDate($enrollment), 'sort_order' => 0]] : [];
    }

    private function defaultDueDate(CourseEnrollment $enrollment)
    {
        return $enrollment->form?->start_date ?? $enrollment->registered_at ?? today();
    }

    private function accounts(): Builder
    {
        return FinancialAccount::withoutGlobalScope(AuthorizationService::DATA_SCOPE);
    }
}
