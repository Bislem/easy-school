<?php

namespace App\Services;

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\SchoolParent;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrivateSchoolApplicantMatcher
{
    public function parent(array $data): SchoolParent
    {
        $email = Str::lower($data['email']);
        $phone = $this->phone($data['phone']);
        $parent = SchoolParent::query()->where(fn ($query) => $query
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?", [$phone])
            ->orWhereHas('user', fn ($user) => $user->withoutGlobalScope('tenant')->whereRaw('LOWER(email) = ?', [$email])))
            ->first();
        if ($parent) {
            return $parent;
        }

        $user = User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user && $user->role !== UserRole::PARENT) {
            throw ValidationException::withMessages(['parent_email' => 'Cette adresse e-mail appartient à un compte qui ne peut pas être utilisé comme parent.']);
        }
        if (! $user) {
            $user = User::create([
                'tenant_id' => null, 'name' => trim($data['first_name'].' '.$data['last_name']),
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                'email' => $email, 'phone' => $data['phone'], 'password' => Str::random(64),
                'role' => UserRole::PARENT, 'is_active' => true, 'can_login' => false,
            ]);
        }

        return SchoolParent::firstOrCreate(
            ['user_id' => $user->id],
            ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'phone' => $data['phone'], 'relationship' => $data['relationship'] ?? null],
        );
    }

    public function student(array $data, ?SchoolParent $parent = null): Student
    {
        $query = Student::query();
        if (! empty($data['email'])) {
            $student = (clone $query)->whereRaw('LOWER(email) = ?', [Str::lower($data['email'])])->first();
            if ($student) {
                return $student;
            }
        }
        if (! empty($data['phone'])) {
            $student = (clone $query)->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?", [$this->phone($data['phone'])])
                ->whereDate('birth_date', $data['birth_date'])->first();
            if ($student) {
                return $student;
            }
        }

        $student = $parent ? $query->whereHas('parents', fn ($parents) => $parents->whereKey($parent->id))
            ->whereRaw('LOWER(first_name) = ?', [Str::lower($data['first_name'])])
            ->whereRaw('LOWER(last_name) = ?', [Str::lower($data['last_name'])])
            ->whereDate('birth_date', $data['birth_date'])->first() : null;
        if ($student) {
            return $student;
        }

        return Student::create([
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'email' => empty($data['email']) ? null : Str::lower($data['email']), 'phone' => $data['phone'] ?? null,
            'birth_date' => $data['birth_date'], 'address' => $data['address'] ?? null,
            'parent_phone' => $data['parent_phone'], 'registration_date' => now()->toDateString(),
            'status' => StudentStatus::ACTIVE, 'is_active' => true,
        ]);
    }

    public function link(SchoolParent $parent, Student $student): void
    {
        if (! $parent->students()->whereKey($student->id)->exists()) {
            $parent->students()->attach($student->id, ['tenant_id' => app(TenantContext::class)->id(), 'is_primary' => true, 'is_visible' => true]);
        }
    }

    private function phone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone) ?: $phone;
    }
}
