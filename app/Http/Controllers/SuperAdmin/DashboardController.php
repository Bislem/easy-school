<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Models\ContactRequest;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => [
                'schools' => Tenant::count(),
                'activeSchools' => Tenant::where('status', 'active')->count(),
                'suspendedSchools' => Tenant::where('status', 'suspended')->count(),
                'users' => User::withoutGlobalScopes()->whereNotNull('tenant_id')->count(),
                'students' => Student::withoutGlobalScopes()->count(),
                'newContacts' => ContactRequest::where('status', 'new')->count(),
            ],
            'recentSchools' => Tenant::latest()->limit(6)->get(['id', 'name', 'slug', 'status', 'created_at']),
        ]);
    }
}
