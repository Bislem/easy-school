<?php

namespace App\Http\Middleware;

use App\Models\AcademicYear;
use App\Models\CompanySetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Services\AuthorizationService;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $superAdminPath = trim((string) config('saas.super_admin_path'), '/');
        $isPlatformAdmin = $superAdminPath !== '' && $request->is($superAdminPath.'/*');
        $settings = $isPlatformAdmin
            ? new CompanySetting(CompanySetting::defaults())
            : CompanySetting::current();

        if ($settings->exists) {
            $settings->load('files');
        }

        $authenticatedUser = $isPlatformAdmin
            ? auth('super_admin')->user()
            : $request->user();
        $effectivePermissions = (! $isPlatformAdmin && $authenticatedUser && Schema::hasTable('roles'))
            ? app(AuthorizationService::class)->permissions($authenticatedUser)
            : [];
        $academicYears = collect();
        $selectedAcademicYear = null;
        if (! $isPlatformAdmin && $authenticatedUser?->tenant?->organization_type === 'private_school' && Schema::hasTable('academic_years')) {
            $academicYears = AcademicYear::orderByDesc('start_date')->get(['id', 'name', 'status', 'start_date', 'end_date']);
            $selectedId = $request->session()->get('academic_year_id');
            $selectedAcademicYear = $academicYears->firstWhere('id', (int) $selectedId)
                ?? $academicYears->first(fn ($year) => $year->status->value === 'active')
                ?? $academicYears->first();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'agency' => $settings,
            'school' => $settings,
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $authenticatedUser,
                'tenant' => $authenticatedUser?->tenant?->only(['id', 'name', 'slug', 'logo_url', 'status', 'account_type', 'organization_type', 'demo_expires_at']),
                'permissions' => $effectivePermissions,
            ],
            'academic_years' => $academicYears,
            'current_academic_year' => $selectedAcademicYear,
            'superAdmin' => $isPlatformAdmin ? ['basePath' => '/'.$superAdminPath] : null,
            'unread_notifications_count' => fn () => $isPlatformAdmin ? 0 : ($request->user()?->portalNotifications()->whereNull('read_at')->count() ?? 0),
            'auth_notifications' => fn () => $isPlatformAdmin ? [] : ($request->user()?->portalNotifications()
                ->limit(10)
                ->get(['id', 'type', 'title', 'message', 'data', 'read_at', 'occurred_at']) ?? []),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'csrf_token' => csrf_token(),
            'fileUploadConfig' => [
                'locale' => config('vilt-filepond.locale'),
                'chunkSize' => config('vilt-filepond.chunk_size'),
            ],
            'currency' => [
                'symbol' => config('app.currency_symbol'),
                'code' => config('app.currency_code'),
            ],
            'flash' => [
                'restricted_action' => $request->session()->get('restricted_action'),
                'success' => $request->session()->get('success'),
                'enrollment_pending' => $request->session()->get('enrollment_pending'),
                'private_school_inscription_submitted' => $request->session()->get('private_school_inscription_submitted'),
            ],
        ];
    }
}
