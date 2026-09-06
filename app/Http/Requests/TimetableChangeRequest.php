<?php

namespace App\Http\Requests;

use App\Enums\TimetableSessionStatus;
use App\Enums\UserRole;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimetableChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('timetable.manage') === true;
    }

    public function rules(): array
    {
        return [
            'training_plan_group_id' => ['sometimes', 'integer', TenantRule::exists('training_plan_groups')],
            'course_id' => ['sometimes', 'integer', TenantRule::exists('courses')],
            'teacher_id' => ['sometimes', 'integer', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)],
            'classroom_id' => ['sometimes', 'integer', TenantRule::exists('classrooms')],
            'academic_period_id' => ['sometimes', 'integer', TenantRule::exists('academic_periods')],
            'day' => ['sometimes', 'integer', 'between:1,7'], 'effective_date' => ['nullable', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'], 'end_time' => ['sometimes', 'date_format:H:i'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'], 'status' => ['sometimes', Rule::enum(TimetableSessionStatus::class)],
            'scope' => ['sometimes', 'in:one,all'],
            'temporary' => ['sometimes', 'boolean'],
        ];
    }
}
