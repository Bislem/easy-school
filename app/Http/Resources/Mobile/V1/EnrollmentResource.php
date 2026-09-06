<?php

namespace App\Http\Resources\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $course = $this->form?->course ?? $this->trainingPlanGroup?->plan?->course;
        return ['id' => $this->id, 'status' => $this->status?->value ?? $this->status, 'course' => $course ? ['id' => $course->id, 'title' => $course->title, 'code' => $course->code, 'category' => $course->category, 'duration_hours' => $course->duration_hours] : null, 'group' => $this->trainingPlanGroup ? ['id' => $this->trainingPlanGroup->id, 'name' => $this->trainingPlanGroup->name] : null, 'registered_at' => $this->registered_at?->toIso8601String(), 'finance' => ['price' => $this->final_price, 'paid' => $this->total_paid, 'remaining' => $this->remaining_balance, 'status' => $this->payment_status?->value ?? $this->payment_status]];
    }
}
