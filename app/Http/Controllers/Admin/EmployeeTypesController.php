<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffPermission;
use App\Http\Controllers\Controller;
use App\Models\EmployeeType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeTypesController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(StaffPermission::MANAGE_TYPES->value);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:employee_types,name'],
            'is_teacher' => ['sometimes', 'boolean'],
        ]);
        EmployeeType::create([
            'name' => $validated['name'], 'slug' => $this->uniqueSlug($validated['name']),
            'is_teacher' => (bool) ($validated['is_teacher'] ?? false), 'is_active' => true,
            'sort_order' => ((int) EmployeeType::max('sort_order')) + 1,
        ]);
        return back()->with('success', 'Type d’employé ajouté.');
    }

    public function update(Request $request, EmployeeType $employeeType): RedirectResponse
    {
        Gate::authorize(StaffPermission::MANAGE_TYPES->value);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('employee_types', 'name')->ignore($employeeType)],
            'is_teacher' => ['required', 'boolean'], 'is_active' => ['required', 'boolean'],
        ]);
        if ($employeeType->is_teacher && ! $validated['is_teacher'] && $employeeType->staff()->exists()) {
            return back()->withErrors(['employee_type' => 'La capacité enseignant ne peut pas être retirée tant que ce type est utilisé.']);
        }
        $employeeType->update($validated);
        return back()->with('success', 'Type d’employé mis à jour.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'employee-type'; $slug = $base; $suffix = 2;
        while (EmployeeType::where('slug', $slug)->exists()) $slug = $base.'-'.$suffix++;
        return $slug;
    }
}
