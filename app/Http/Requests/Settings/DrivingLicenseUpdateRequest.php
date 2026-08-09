<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DrivingLicenseUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'birth_date' => ['required', 'date', 'before:today'],
            'driving_license_number' => ['required', 'string', 'max:100', Rule::unique('users', 'driving_license_number')->ignore($this->user()->id)],
            'driving_license_delivered_at' => ['required', 'date', 'before_or_equal:today', 'after:birth_date'],
            'driving_license_authority' => ['required', 'string', 'max:255'],
            'driving_license_copy' => [Rule::requiredIf(!$this->user()->driving_license_path), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
