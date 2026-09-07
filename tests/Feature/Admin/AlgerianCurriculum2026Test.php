<?php

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\SchoolCycle;
use App\Models\SchoolLevel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AlgerianCurriculum2026;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

function curriculumTenant(): array
{
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    app(TenantContext::class)->set($tenant);
    foreach (['Primaire' => ['1AP', '2AP', '3AP', '4AP', '5AP'], 'CEM' => ['1AM', '2AM', '3AM', '4AM'], 'Lycée' => ['1AS', '2AS', '3AS']] as $cycleName => $levels) {
        $cycle = SchoolCycle::create(['name' => $cycleName, 'code' => strtoupper($cycleName), 'sort_order' => 1]);
        foreach ($levels as $order => $code) {
            SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => $code, 'code' => $code, 'sort_order' => $order]);
        }
    }
    app(TenantContext::class)->clear();

    return compact('tenant', 'admin');
}

test('admin loads the complete versioned Algerian curriculum idempotently', function () {
    ['tenant' => $tenant, 'admin' => $admin] = curriculumTenant();
    $this->actingAs($admin)->post('/admin/subjects/load-default-curriculum')->assertSessionHasNoErrors();
    app(TenantContext::class)->set($tenant);
    $count = DB::table('course_school_level')->where('curriculum_code', AlgerianCurriculum2026::CODE)->count();
    expect($count)->toBe(275);
    app(AlgerianCurriculum2026::class)->initialize();
    expect(DB::table('course_school_level')->where('curriculum_code', AlgerianCurriculum2026::CODE)->count())->toBe($count);
    app(TenantContext::class)->clear();
});

test('curriculum creates independent lycee levels and keeps optional subjects disabled', function () {
    ['tenant' => $tenant, 'admin' => $admin] = curriculumTenant();
    $this->actingAs($admin)->post('/admin/subjects/load-default-curriculum');
    app(TenantContext::class)->set($tenant);
    $math = Course::where('code', 'DZ-MATH')->firstOrFail();
    expect($math->schoolLevels()->whereIn('school_levels.code', ['1AM', '2AM', '3AM', '4AM'])->count())->toBe(4);
    expect(SchoolLevel::where('code', '2AS')->whereNotNull('specialization')->count())->toBe(7)
        ->and(SchoolLevel::where('code', '3AS')->whereNotNull('specialization')->count())->toBe(7)
        ->and(DB::table('course_school_level')->whereNotNull('school_stream_id')->count())->toBe(0);
    $amazigh = Course::where('code', 'DZ-AMZ')->firstOrFail();
    expect(DB::table('course_school_level')->where('course_id', $amazigh->id)->where('curriculum_code', AlgerianCurriculum2026::CODE)->where('is_optional', true)->where('is_active', false)->exists())->toBeTrue();
    expect(DB::table('course_school_level')->where('choice_group', 'THIRD_FOREIGN_LANGUAGE')->where('is_optional', true)->where('is_active', false)->count())->toBe(6);
    app(TenantContext::class)->clear();
});

test('loading defaults does not change custom subjects or customized curriculum assignments', function () {
    ['tenant' => $tenant, 'admin' => $admin] = curriculumTenant();
    app(TenantContext::class)->set($tenant);
    $custom = Course::create(['title' => 'Robotique', 'code' => 'ROBOT', 'duration_hours' => 1, 'weekly_hours' => 2, 'price' => 0, 'is_active' => true]);
    app(TenantContext::class)->clear();
    $this->actingAs($admin)->post('/admin/subjects/load-default-curriculum');
    app(TenantContext::class)->set($tenant);
    $assignment = DB::table('course_school_level')->where('curriculum_code', AlgerianCurriculum2026::CODE)->first();
    DB::table('course_school_level')->where('id', $assignment->id)->update(['is_active' => false, 'display_order' => 99]);
    app(AlgerianCurriculum2026::class)->initialize();
    expect($custom->fresh()->title)->toBe('Robotique')
        ->and(DB::table('course_school_level')->where('id', $assignment->id)->value('display_order'))->toBe(99);
    app(TenantContext::class)->clear();
});

test('generated subjects can be updated through their specialized level selections', function () {
    ['tenant' => $tenant, 'admin' => $admin] = curriculumTenant();
    $this->actingAs($admin)->post('/admin/subjects/load-default-curriculum');
    app(TenantContext::class)->set($tenant);
    $subject = Course::where('code', 'DZ-MATH')->with('schoolLevels')->firstOrFail();
    $levelIdsAsSentByTheEditForm = $subject->schoolLevels->where('pivot.is_active', true)->pluck('id')->all();
    $curriculumAssignments = DB::table('course_school_level')->where('course_id', $subject->id)->whereNotNull('curriculum_code')->count();
    $editableAssignment = DB::table('course_school_level')->where('course_id', $subject->id)->whereNotNull('curriculum_code')->first();
    $levelIdsAsSentByTheEditForm = array_values(array_diff($levelIdsAsSentByTheEditForm, [$editableAssignment->school_level_id]));
    app(TenantContext::class)->clear();

    $this->actingAs($admin)->put("/admin/subjects/{$subject->id}", [
        'title' => 'Mathématiques avancées', 'title_ar' => 'الرياضيات', 'code' => 'DZ-MATH',
        'category' => 'Programme national algérien', 'color' => '#2563eb', 'weekly_hours' => 5,
        'description' => null, 'is_specialized' => false, 'is_active' => true,
        'required_room_types' => [], 'school_level_ids' => $levelIdsAsSentByTheEditForm, 'teacher_ids' => [],
    ])->assertSessionHasNoErrors();

    app(TenantContext::class)->set($tenant);
    expect($subject->fresh()->title)->toBe('Mathématiques avancées')
        ->and(DB::table('course_school_level')->where('course_id', $subject->id)->whereNotNull('curriculum_code')->count())->toBe($curriculumAssignments)
        ->and(DB::table('course_school_level')->where('course_id', $subject->id)->whereNull('curriculum_code')->count())->toBe(0)
        ->and((bool) DB::table('course_school_level')->where('id', $editableAssignment->id)->value('is_active'))->toBeFalse();
    app(TenantContext::class)->clear();
});
