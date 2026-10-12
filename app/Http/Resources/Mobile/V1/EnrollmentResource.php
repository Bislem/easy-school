<?php

namespace App\Http\Resources\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $course = $this->form?->course ?? $this->trainingPlanGroup?->plan?->course;
        $account = $this->financialAccount;
        return ['id' => $this->id, 'status' => $this->status?->value ?? $this->status, 'course' => $course ? ['id' => $course->id, 'title' => $course->title, 'code' => $course->code, 'category' => $course->category, 'duration_hours' => $course->duration_hours] : null, 'group' => $this->trainingPlanGroup ? ['id' => $this->trainingPlanGroup->id, 'name' => $this->trainingPlanGroup->name] : null, 'registered_at' => $this->registered_at?->toIso8601String(), 'finance' => ['price' => $account ? (float) $account->expected_total + (float) $account->adjustment_total : null, 'paid' => $account?->paid_total, 'remaining' => $account?->balance, 'status' => $account?->status]];
    }
}
