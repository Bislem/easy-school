<?php

namespace App\Http\Requests;

use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class AcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('timetable.manage') === true; }
    public function rules(): array
    {
        $period = $this->route('academicPeriod');
        return [
            'name' => ['required', 'string', 'max:100'],
            'academic_year' => ['required', 'string', 'max:20', TenantRule::unique('academic_periods', 'academic_year')->where('name', $this->input('name'))->ignore($period)],
            'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
        ];
    }
}
