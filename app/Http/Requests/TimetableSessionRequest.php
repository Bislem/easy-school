<?php

namespace App\Http\Requests;

use App\Enums\TimetableRecurrence;
use App\Enums\TimetableSessionStatus;
use App\Enums\UserRole;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimetableSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('timetable.manage') === true;
    }

    public function rules(): array
    {
        return [
            'school_group_id' => ['required', 'integer', TenantRule::exists('school_groups')],
            'course_id' => ['required', 'integer', TenantRule::exists('courses')->where('entity_type', 'subject')],
            'teacher_id' => ['required', 'integer', TenantRule::exists('users')->where('role', UserRole::TEACHER->value)],
            'classroom_id' => ['nullable', 'integer', TenantRule::exists('classrooms')],
            'academic_period_id' => ['nullable', 'integer', TenantRule::exists('academic_periods')],
            'day' => ['required', 'integer', 'between:1,7'],
            'effective_date' => ['nullable', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'recurrence' => ['sometimes', Rule::enum(TimetableRecurrence::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::enum(TimetableSessionStatus::class)],
            'change_type' => ['sometimes', 'nullable', Rule::in(['exception', 'moved', 'teacher_replacement', 'room_change', 'temporary_room_change'])],
            'scope' => ['sometimes', 'in:one,all'],
            'except_session_id' => ['sometimes', 'integer', TenantRule::exists('timetable_sessions')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('recurrence', 'weekly') === TimetableRecurrence::ONCE->value && ! $this->filled('effective_date')) {
                $validator->errors()->add('effective_date', 'An effective date is required for a non-recurring session.');
            }
        });
    }
}
