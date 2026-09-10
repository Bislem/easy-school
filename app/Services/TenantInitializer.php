<?php

namespace App\Services;

use App\Models\BadgeTemplate;
use App\Models\CompanySetting;
use App\Models\EmployeeType;
use App\Models\SchoolCycle;
use App\Models\Tenant;
use App\Models\TimetableSetting;

final class TenantInitializer
{
    public function __construct(private readonly DefaultTenantRoles $defaultRoles) {}

    public function initialize(Tenant $tenant): void
    {
        CompanySetting::create([
            ...CompanySetting::defaults(),
            'trading_name' => $tenant->name,
            'legal_name' => $tenant->name,
            'address_line_1' => $tenant->address,
            'city' => $tenant->commune,
            'phone' => $tenant->phone,
            'email' => $tenant->email,
        ]);

        $types = [
            ['name' => 'Enseignant', 'slug' => 'teacher', 'is_teacher' => true],
            ['name' => 'Secrétaire', 'slug' => 'secretary'],
            ['name' => 'Administrateur', 'slug' => 'administrator'],
            ['name' => 'Comptable', 'slug' => 'accountant'],
            ['name' => 'Réceptionniste', 'slug' => 'receptionist'],
            ['name' => 'Maintenance', 'slug' => 'maintenance'],
            ['name' => 'Manager', 'slug' => 'manager'],
            ['name' => 'Autre', 'slug' => 'other'],
        ];

        foreach ($types as $index => $type) {
            EmployeeType::create($type + ['is_teacher' => false, 'is_active' => true, 'sort_order' => $index]);
        }

        BadgeTemplate::create([
            'name' => 'Carte Easy School', 'slug' => 'easy-school-default', 'is_default' => true,
            'primary_color' => '#f97316', 'secondary_color' => '#111827', 'text_color' => '#ffffff',
        ]);

        $cycles = ['Primaire' => ['1AP', '2AP', '3AP', '4AP', '5AP'], 'CEM' => ['1AM', '2AM', '3AM', '4AM'], 'Lycée' => ['1AS', '2AS', '3AS']];
        foreach ($cycles as $cycleOrder => $levels) {
            $cycle = SchoolCycle::create(['name' => $cycleOrder, 'code' => $cycleOrder === 'Lycée' ? 'LYCEE' : strtoupper($cycleOrder), 'sort_order' => array_search($cycleOrder, array_keys($cycles), true)]);
            foreach ($levels as $order => $code) {
                $cycle->levels()->create(['name' => $code, 'code' => $code, 'sort_order' => $order]);
            }
        }

        TimetableSetting::create(TimetableSetting::defaults());
        $this->defaultRoles->provision($tenant);
    }
}
