<?php

use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AttendanceException;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\MobileMembership;
use App\Models\PlatformNotification;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\SchoolParent;
use App\Models\SchoolSite;
use App\Models\SchoolSubject;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\Tenant;
use App\Models\TimetableSession;
use App\Models\User;
use App\Services\DefaultTenantRoles;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use MohamedGaldi\ViltFilepond\Models\TempFile;

function parentPortalAccount(?Tenant $tenant = null): array
{
    $tenant ??= Tenant::factory()->create(['organization_type' => 'private_school']);
    app(TenantContext::class)->set($tenant);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::PARENT]);
    $parent = SchoolParent::create(['user_id' => $user->id, 'first_name' => 'Nadia', 'last_name' => 'Kaci']);
    app(TenantContext::class)->clear();

    return compact('tenant', 'user', 'parent');
}

function parentPortalStudent(Tenant $tenant, string $firstName = 'Lina'): Student
{
    app(TenantContext::class)->set($tenant);
    $student = Student::create(['first_name' => $firstName, 'last_name' => 'Kaci', 'phone' => '0550000000', 'is_active' => true]);
    app(TenantContext::class)->clear();

    return $student;
}

function parentAcademicContext(Tenant $tenant, string $yearName = '2026/2027', string $status = 'active'): array
{
    app(TenantContext::class)->set($tenant);
    $cycle = SchoolCycle::create(['name' => 'Primaire '.$yearName, 'code' => 'PRI'.str_replace(['/', '-'], '', $yearName), 'sort_order' => 1]);
    $level = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => '3AP', 'code' => '3AP'.str_replace(['/', '-'], '', $yearName), 'sort_order' => 1]);
    $year = AcademicYear::create(['name' => $yearName, 'start_date' => str_starts_with($yearName, '2025') ? '2025-09-01' : '2026-09-01', 'end_date' => str_starts_with($yearName, '2025') ? '2026-06-30' : '2027-06-30', 'status' => $status]);
    $period = AcademicPeriod::create(['academic_year_id' => $year->id, 'name' => 'Trimestre 1', 'number' => 1, 'academic_year' => $yearName, 'starts_on' => $year->start_date, 'ends_on' => $year->start_date->copy()->addMonths(3), 'status' => 'active']);
    $site = SchoolSite::create(['name' => 'Site principal', 'code' => 'SITE'.$tenant->id.$year->id, 'wilaya' => 'Alger', 'is_active' => true]);
    $room = Classroom::create(['school_site_id' => $site->id, 'name' => '04', 'code' => 'R'.$tenant->id.$year->id, 'type' => 'classroom', 'capacity' => 30, 'is_active' => true, 'is_available' => true]);
    $group = SchoolGroup::create(['academic_year_id' => $year->id, 'school_level_id' => $level->id, 'classroom_id' => $room->id, 'name' => 'Groupe A', 'code' => 'GA'.$year->id, 'is_active' => true]);
    $subject = SchoolSubject::create(['title' => 'Mathématiques', 'code' => 'MATH'.$tenant->id.$year->id, 'duration_hours' => 1, 'price' => 0, 'is_active' => true]);
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TEACHER]);
    $creator = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    app(TenantContext::class)->clear();

    return compact('year', 'period', 'level', 'group', 'room', 'subject', 'teacher', 'creator');
}

function enrollParentStudent(Tenant $tenant, SchoolParent $parent, Student $student, array $context): StudentAcademicEnrollment
{
    app(TenantContext::class)->set($tenant);
    $parent->students()->syncWithoutDetaching([$student->id]);
    $enrollment = StudentAcademicEnrollment::create(['academic_year_id' => $context['year']->id, 'student_id' => $student->id, 'school_level_id' => $context['level']->id, 'school_group_id' => $context['group']?->id, 'status' => 'enrolled', 'enrollment_date' => $context['year']->start_date]);
    app(TenantContext::class)->clear();

    return $enrollment;
}

function parentAssessment(Tenant $tenant, array $context, StudentAcademicEnrollment $enrollment, array $attributes = []): array
{
    app(TenantContext::class)->set($tenant);
    $assessment = Assessment::create(array_merge([
        'academic_year_id' => $context['year']->id, 'academic_period_id' => $context['period']->id,
        'school_level_id' => $context['level']->id, 'subject_id' => $context['subject']->id,
        'teacher_id' => $context['teacher']->id, 'name' => 'Composition de mathématiques',
        'assessment_type' => 'exam', 'assessment_date' => '2026-09-20', 'maximum_grade' => 20,
        'weight' => 3, 'status' => 'locked', 'published_at' => now(), 'published_by' => $context['creator']->id,
    ], $attributes));
    $assessment->groups()->attach($context['group']->id, ['tenant_id' => $tenant->id]);
    $grade = Grade::create(['assessment_id' => $assessment->id, 'student_id' => $enrollment->student_id, 'student_academic_enrollment_id' => $enrollment->id, 'value' => 16, 'status' => 'graded', 'entered_by' => $context['teacher']->id]);
    app(TenantContext::class)->clear();

    return compact('assessment', 'grade');
}

test('parent can access the dedicated portal and non parents cannot', function () {
    ['tenant' => $tenant, 'user' => $parent] = parentPortalAccount();
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TEACHER]);

    $this->actingAs($parent)->get('/parent')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Parent/Dashboard'));
    $this->actingAs($teacher)->get('/parent')->assertForbidden();
});

test('parent sees every linked child without per-child visibility controls', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $linked = parentPortalStudent($tenant, 'Lina');
    $hidden = parentPortalStudent($tenant, 'Hidden');
    $unrelated = parentPortalStudent($tenant, 'Sara');
    app(TenantContext::class)->set($tenant);
    $parent->students()->attach($linked, ['is_visible' => true]);
    $parent->students()->attach($hidden, ['is_visible' => false]);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->get('/parent/children')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Parent/Children/Index')->has('children', 2));
    $this->actingAs($user)->get("/parent/children/{$unrelated->id}")->assertForbidden();
    $this->actingAs($user)->get("/parent/children/{$hidden->id}")->assertOk();
});

test('parent can update details only for a linked child', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $child = parentPortalStudent($tenant);
    $unrelated = parentPortalStudent($tenant, 'Sara');
    app(TenantContext::class)->set($tenant);
    $parent->students()->attach($child);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->patch("/parent/children/{$child->id}/details", [
        'first_name' => 'Lina',
        'last_name' => 'Kaci',
        'email' => 'lina@example.test',
        'phone' => '0661000000',
        'birth_date' => '2015-04-12',
        'address' => '12 rue des Écoles, Alger',
    ])->assertRedirect();

    expect($child->fresh()->email)->toBe('lina@example.test')
        ->and($child->fresh()->address)->toBe('12 rue des Écoles, Alger');
    $this->patch("/parent/children/{$unrelated->id}/details", ['first_name' => 'Sara', 'last_name' => 'Kaci', 'phone' => '0770000000'])->assertForbidden();
});

test('parent can view and add documents to a linked child folder', function () {
    Storage::fake(config('vilt-filepond.storage_disk'));
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $child = parentPortalStudent($tenant);
    app(TenantContext::class)->set($tenant);
    $parent->students()->attach($child);
    Storage::disk(config('vilt-filepond.storage_disk'))->put('temp-files/parent-doc/certificate.pdf', 'pdf content');
    TempFile::create([
        'folder' => 'parent-doc', 'original_name' => 'certificat.pdf', 'filename' => 'certificate.pdf',
        'path' => 'temp-files/parent-doc/certificate.pdf', 'mime_type' => 'application/pdf', 'size' => 11,
    ]);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->put("/parent/children/{$child->id}/documents", [
        'document_temp_folders' => ['parent-doc'], 'document_removed_files' => [],
    ])->assertRedirect();

    $file = $child->files()->where('collection', 'documents')->firstOrFail();
    expect($file->original_name)->toBe('certificat.pdf');
    $this->get("/parent/children/{$child->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Students/Show')->where('parentView', true)
        ->where('student.files.0.id', $file->id));
});

test('parent can edit the medical folder and reply to observations', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $child = parentPortalStudent($tenant);
    app(TenantContext::class)->set($tenant);
    $parent->students()->attach($child);
    $teacher = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::TEACHER]);
    $thread = $child->observations()->create(['author_id' => $teacher->id, 'message' => 'Merci de nous informer.']);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->put("/parent/children/{$child->id}/medical", [
        'blood_type' => 'O+', 'allergies' => 'Pénicilline', 'emergency_contact_name' => 'Nadia Kaci',
        'emergency_contact_phone' => '0550000000', 'medical_temp_folders' => [], 'medical_removed_files' => [],
    ])->assertRedirect();
    $this->post("/portal/students/{$child->id}/observations", [
        'parent_id' => $thread->id, 'message' => 'Information bien reçue.',
    ])->assertRedirect();
    $this->post("/portal/students/{$child->id}/observations", [
        'parent_id' => null, 'message' => 'Mon enfant suit un nouveau traitement.',
    ])->assertRedirect();

    expect($child->fresh()->blood_type)->toBe('O+')
        ->and($child->fresh()->allergies)->toBe('Pénicilline')
        ->and($thread->replies()->where('author_id', $user->id)->where('message', 'Information bien reçue.')->exists())->toBeTrue()
        ->and($child->observations()->whereNull('parent_id')->where('author_id', $user->id)->where('message', 'Mon enfant suit un nouveau traitement.')->exists())->toBeTrue();
});

test('parent continues to see linked children who are stopped or completed', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $active = parentPortalStudent($tenant, 'Active');
    $historical = parentPortalStudent($tenant, 'Historical');
    app(TenantContext::class)->set($tenant);
    $historical->update(['is_active' => false, 'status' => 'completed']);
    $parent->students()->attach([$active->id, $historical->id]);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->get('/parent/children')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('children', 2));
});

test('parent cannot access a student from another tenant', function () {
    ['user' => $user] = parentPortalAccount();
    $otherTenant = Tenant::factory()->create(['organization_type' => 'private_school']);
    $foreign = parentPortalStudent($otherTenant, 'Foreign');

    $this->actingAs($user)->get("/parent/children/{$foreign->id}")->assertForbidden();
});

test('parent sees children from every active school membership and can switch schools', function () {
    $firstTenant = Tenant::factory()->create(['name' => 'École A', 'organization_type' => 'private_school']);
    $secondTenant = Tenant::factory()->create(['name' => 'École B', 'organization_type' => 'private_school']);
    app(TenantContext::class)->set($firstTenant);
    $user = User::factory()->create(['tenant_id' => null, 'role' => UserRole::PARENT]);
    $firstParent = SchoolParent::create(['user_id' => $user->id, 'first_name' => 'Nadia', 'last_name' => 'Kaci']);
    $firstChild = Student::create(['first_name' => 'Lina', 'last_name' => 'Kaci', 'phone' => '0550000001', 'is_active' => true]);
    $firstParent->students()->attach($firstChild);
    app(TenantContext::class)->set($secondTenant);
    $secondParent = SchoolParent::create(['user_id' => $user->id, 'first_name' => 'Nadia', 'last_name' => 'Kaci']);
    $secondChild = Student::create(['first_name' => 'Yanis', 'last_name' => 'Kaci', 'phone' => '0550000002', 'is_active' => true]);
    $secondParent->students()->attach($secondChild);
    app(TenantContext::class)->clear();
    MobileMembership::create(['user_id' => $user->id, 'tenant_id' => $firstTenant->id, 'role' => UserRole::PARENT, 'parent_id' => $firstParent->id, 'is_active' => true]);
    MobileMembership::create(['user_id' => $user->id, 'tenant_id' => $secondTenant->id, 'role' => UserRole::PARENT, 'parent_id' => $secondParent->id, 'is_active' => true]);

    $this->actingAs($user)->get('/parent/children')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('children', 2)
        ->where('portal.children.0.school', 'École A')
        ->where('portal.children.1.school', 'École B'));
    $this->post('/parent/selected-child', ['student_id' => $secondChild->id])->assertRedirect();
    $this->get('/parent')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('portal.selected_child_id', $secondChild->id)
        ->where('portal.children.1.school', 'École B'));
});

test('school parent activation changes create a platform billing notification', function () {
    $tenant = Tenant::factory()->create(['organization_type' => 'private_school', 'status' => 'active']);
    app(DefaultTenantRoles::class)->provision($tenant);
    app(TenantContext::class)->set($tenant);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::ADMIN]);
    $user = User::factory()->create(['tenant_id' => null, 'role' => UserRole::PARENT]);
    $parent = SchoolParent::create(['user_id' => $user->id, 'first_name' => 'Nadia', 'last_name' => 'Kaci']);
    $membership = MobileMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => UserRole::PARENT, 'parent_id' => $parent->id, 'is_active' => true]);
    app(TenantContext::class)->clear();

    $this->actingAs($admin)->patch("/admin/parents/{$parent->id}/toggle")->assertRedirect();

    expect($membership->fresh()->is_active)->toBeFalse();
    $notification = PlatformNotification::latest()->first();
    expect($notification->type)->toBe('parent_account.disabled')
        ->and($notification->tenant_id)->toBe($tenant->id)
        ->and($notification->data['active_parent_accounts'])->toBe(0);
});

test('selected child must belong to parent and multiple children persist in session', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $first = parentPortalStudent($tenant, 'Lina');
    $second = parentPortalStudent($tenant, 'Yasmine');
    $other = parentPortalStudent($tenant, 'Other');
    app(TenantContext::class)->set($tenant);
    $parent->students()->attach([$first->id, $second->id]);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->post('/parent/selected-child', ['student_id' => $second->id])->assertRedirect();
    $this->get('/parent')->assertOk()->assertInertia(fn (Assert $page) => $page->where('portal.selected_child_id', $second->id)->has('portal.children', 2));
    $this->post('/parent/selected-child', ['student_id' => $other->id])->assertForbidden();
});

test('single child is selected automatically and dashboard uses active academic year', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    app(TenantContext::class)->set($tenant);
    $cycle = SchoolCycle::create(['name' => 'Primaire', 'code' => 'PRI', 'sort_order' => 1]);
    $level = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => '3AP', 'code' => '3AP', 'sort_order' => 1]);
    $active = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-06-30', 'status' => 'active']);
    $old = AcademicYear::create(['name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-06-30', 'status' => 'draft']);
    $student = Student::create(['first_name' => 'Lina', 'last_name' => 'Kaci', 'phone' => '0550000000', 'is_active' => true]);
    $parent->students()->attach($student);
    StudentAcademicEnrollment::create(['academic_year_id' => $active->id, 'student_id' => $student->id, 'school_level_id' => $level->id, 'status' => 'enrolled', 'enrollment_date' => '2026-09-01']);
    StudentAcademicEnrollment::create(['academic_year_id' => $old->id, 'student_id' => $student->id, 'school_level_id' => $level->id, 'status' => 'completed', 'enrollment_date' => '2025-09-01']);
    $old->update(['status' => 'archived']);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->get('/parent')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('portal.selected_child_id', $student->id)->where('portal.academic_year.id', $active->id)
        ->where('portal.children.0.level', '3AP'));
});

test('parent with no linked children receives the empty portal state', function () {
    ['user' => $user] = parentPortalAccount();
    $this->actingAs($user)->get('/parent')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('portal.children', 0)->where('portal.selected_child_id', null)->where('today', null));
});

test('parent timetable uses the selected child group and academic year', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $child = parentPortalStudent($tenant);
    enrollParentStudent($tenant, $parent, $child, $context);
    app(TenantContext::class)->set($tenant);
    $session = TimetableSession::create(['academic_year_id' => $context['year']->id, 'school_group_id' => $context['group']->id, 'course_id' => $context['subject']->id, 'teacher_id' => $context['teacher']->id, 'classroom_id' => $context['room']->id, 'academic_period_id' => $context['period']->id, 'day' => 1, 'start_time' => '08:00', 'end_time' => '09:00', 'recurrence' => 'weekly', 'status' => 'published']);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->get('/parent/timetable')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Parent/Timetable')->where('sessions.0.id', $session->id)->where('sessions.0.subject', 'Mathématiques')
        ->where('context.group', 'Groupe A')->where('portal.academic_year.id', $context['year']->id));
});

test('timetable returns distinct empty states for no group and no sessions', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $withoutGroup = parentPortalStudent($tenant);
    $contextWithoutGroup = [...$context, 'group' => null];
    enrollParentStudent($tenant, $parent, $withoutGroup, $contextWithoutGroup);
    $this->actingAs($user)->get('/parent/timetable')->assertInertia(fn (Assert $page) => $page->where('hasEnrollment', true)->where('hasGroup', false)->has('sessions', 0));

    app(TenantContext::class)->set($tenant);
    StudentAcademicEnrollment::where('student_id', $withoutGroup->id)->update(['school_group_id' => $context['group']->id]);
    app(TenantContext::class)->clear();
    $this->get('/parent/timetable')->assertInertia(fn (Assert $page) => $page->where('hasGroup', true)->has('sessions', 0));
});

test('absence page exposes only selected child records and supports filters', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $child = parentPortalStudent($tenant);
    $other = parentPortalStudent($tenant, 'Other');
    enrollParentStudent($tenant, $parent, $child, $context);
    app(TenantContext::class)->set($tenant);
    $session = TimetableSession::create(['academic_year_id' => $context['year']->id, 'school_group_id' => $context['group']->id, 'course_id' => $context['subject']->id, 'teacher_id' => $context['teacher']->id, 'classroom_id' => $context['room']->id, 'academic_period_id' => $context['period']->id, 'day' => 1, 'start_time' => '08:00', 'end_time' => '09:00', 'recurrence' => 'weekly', 'status' => 'published']);
    $visible = AttendanceException::create(['academic_year_id' => $context['year']->id, 'date' => '2026-09-15', 'timetable_session_id' => $session->id, 'person_type' => 'STUDENT', 'student_id' => $child->id, 'status' => 'ABSENT', 'created_by' => $context['creator']->id]);
    AttendanceException::create(['academic_year_id' => $context['year']->id, 'date' => '2026-09-16', 'timetable_session_id' => $session->id, 'person_type' => 'STUDENT', 'student_id' => $child->id, 'status' => 'EXCUSED', 'justification' => 'Certificat', 'justified_at' => now(), 'created_by' => $context['creator']->id]);
    AttendanceException::create(['academic_year_id' => $context['year']->id, 'date' => '2026-09-15', 'timetable_session_id' => $session->id, 'person_type' => 'STUDENT', 'student_id' => $other->id, 'status' => 'ABSENT', 'created_by' => $context['creator']->id]);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->get('/parent/absences?justification=unjustified&subject_id='.$context['subject']->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Parent/Absences')->has('absences', 1)->where('absences.0.id', $visible->id)->where('summary.total', 2)->where('summary.unjustified', 1));
});

test('parent can submit a justification only for the selected childs absence', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $child = parentPortalStudent($tenant, 'Lina');
    $other = parentPortalStudent($tenant, 'Sara');
    enrollParentStudent($tenant, $parent, $child, $context);
    app(TenantContext::class)->set($tenant);
    $absence = AttendanceException::create(['academic_year_id' => $context['year']->id, 'date' => '2026-09-15', 'person_type' => 'STUDENT', 'student_id' => $child->id, 'status' => 'ABSENT', 'created_by' => $context['creator']->id]);
    $foreignAbsence = AttendanceException::create(['academic_year_id' => $context['year']->id, 'date' => '2026-09-15', 'person_type' => 'STUDENT', 'student_id' => $other->id, 'status' => 'ABSENT', 'created_by' => $context['creator']->id]);
    app(TenantContext::class)->clear();
    Storage::fake('local');

    $this->actingAs($user)->patch("/parent/absences/{$absence->id}/justification", [
        'justification' => 'Consultation médicale avec certificat.',
        'attachment' => UploadedFile::fake()->create('certificat-medical.pdf', 120, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();

    app(TenantContext::class)->set($tenant);
    expect($absence->refresh()->justification)->toBe('Consultation médicale avec certificat.')
        ->and($absence->parent_justification_submitted_at)->not->toBeNull()
        ->and($absence->parent_justification_submitted_by)->toBe($user->id)
        ->and($absence->justified_at)->toBeNull()
        ->and($absence->parent_justification_attachment_name)->toBe('certificat-medical.pdf');
    Storage::disk('local')->assertExists($absence->parent_justification_attachment_path);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->get("/parent/absences/{$absence->id}/attachment")
        ->assertOk()->assertDownload('certificat-medical.pdf');

    $this->patch("/parent/absences/{$foreignAbsence->id}/justification", [
        'justification' => 'Tentative interdite.',
    ])->assertNotFound();
});

test('parent cannot replace a justification already submitted or approved', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $child = parentPortalStudent($tenant);
    enrollParentStudent($tenant, $parent, $child, $context);
    app(TenantContext::class)->set($tenant);
    $absence = AttendanceException::create(['academic_year_id' => $context['year']->id, 'date' => '2026-09-15', 'person_type' => 'STUDENT', 'student_id' => $child->id, 'status' => 'ABSENT', 'justification' => 'Déjà envoyée', 'parent_justification_submitted_at' => now(), 'parent_justification_submitted_by' => $user->id, 'created_by' => $context['creator']->id]);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->patch("/parent/absences/{$absence->id}/justification", [
        'justification' => 'Modification interdite.',
    ])->assertStatus(422);
});

test('historical absences use historical enrollment context without mixing years', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $active = parentAcademicContext($tenant);
    $historical = parentAcademicContext($tenant, '2025/2026', 'draft');
    $child = parentPortalStudent($tenant);
    enrollParentStudent($tenant, $parent, $child, $active);
    enrollParentStudent($tenant, $parent, $child, $historical);
    app(TenantContext::class)->set($tenant);
    $historical['year']->update(['status' => 'archived']);
    $oldAbsence = AttendanceException::create(['academic_year_id' => $historical['year']->id, 'date' => '2025-09-15', 'person_type' => 'STUDENT', 'student_id' => $child->id, 'status' => 'ABSENT', 'created_by' => $historical['creator']->id]);
    AttendanceException::create(['academic_year_id' => $active['year']->id, 'date' => '2026-09-15', 'person_type' => 'STUDENT', 'student_id' => $child->id, 'status' => 'ABSENT', 'created_by' => $active['creator']->id]);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->get('/parent/absences?academic_year_id='.$historical['year']->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('portal.academic_year.id', $historical['year']->id)->where('context.group', 'Groupe A')->has('absences', 1)->where('absences.0.id', $oldAbsence->id));
});

test('switching children changes timetable and absence datasets and rejects unlinked child', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $first = parentPortalStudent($tenant, 'First');
    $second = parentPortalStudent($tenant, 'Second');
    $unlinked = parentPortalStudent($tenant, 'Unlinked');
    enrollParentStudent($tenant, $parent, $first, $context);
    enrollParentStudent($tenant, $parent, $second, $context);
    app(TenantContext::class)->set($tenant);
    $absence = AttendanceException::create(['academic_year_id' => $context['year']->id, 'date' => '2026-09-15', 'person_type' => 'STUDENT', 'student_id' => $second->id, 'status' => 'ABSENT', 'created_by' => $context['creator']->id]);
    app(TenantContext::class)->clear();
    $this->actingAs($user)->post('/parent/selected-child', ['student_id' => $second->id])->assertRedirect();
    $this->get('/parent/absences')->assertInertia(fn (Assert $page) => $page->where('portal.selected_child_id', $second->id)->where('absences.0.id', $absence->id));
    $this->post('/parent/selected-child', ['student_id' => $unlinked->id])->assertForbidden();
});

test('parent sees published grades while draft and unpublished results stay hidden', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $child = parentPortalStudent($tenant);
    $enrollment = enrollParentStudent($tenant, $parent, $child, $context);
    ['assessment' => $published] = parentAssessment($tenant, $context, $enrollment);
    parentAssessment($tenant, $context, $enrollment, ['name' => 'Brouillon secret', 'status' => 'draft', 'published_at' => null, 'published_by' => null]);
    parentAssessment($tenant, $context, $enrollment, ['name' => 'Non publié', 'published_at' => null, 'published_by' => null]);

    $this->actingAs($user)->get('/parent/grades')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Parent/Grades')->has('results', 1)->has('results.0.assessments', 1)
        ->where('results.0.assessments.0.id', $published->id)->where('results.0.average', 16));
});

test('grade calculations use assessment weights from the authoritative service', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $child = parentPortalStudent($tenant);
    $enrollment = enrollParentStudent($tenant, $parent, $child, $context);
    ['grade' => $testGrade] = parentAssessment($tenant, $context, $enrollment, ['name' => 'Test', 'assessment_type' => 'test', 'weight' => 1, 'maximum_grade' => 10]);
    ['grade' => $examGrade] = parentAssessment($tenant, $context, $enrollment, ['name' => 'Examen', 'weight' => 3]);
    app(TenantContext::class)->set($tenant);
    Grade::whereKey($testGrade->id)->update(['value' => 10]);
    Grade::whereKey($examGrade->id)->update(['value' => 10]);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->get('/parent/grades')->assertInertia(fn (Assert $page) => $page->where('results.0.average', 12.5));
});

test('grades respect academic period and historical enrollment context', function () {
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $active = parentAcademicContext($tenant);
    $old = parentAcademicContext($tenant, '2025/2026', 'draft');
    $child = parentPortalStudent($tenant);
    enrollParentStudent($tenant, $parent, $child, $active);
    $oldEnrollment = enrollParentStudent($tenant, $parent, $child, $old);
    ['assessment' => $historical] = parentAssessment($tenant, $old, $oldEnrollment, ['assessment_date' => '2025-10-10']);
    app(TenantContext::class)->set($tenant);
    $old['year']->update(['status' => 'archived']);
    app(TenantContext::class)->clear();

    $this->actingAs($user)->get('/parent/grades?academic_year_id='.$old['year']->id.'&academic_period_id='.$old['period']->id)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('portal.academic_year.id', $old['year']->id)->where('context.group', 'Groupe A')
        ->where('results.0.assessments.0.id', $historical->id));
});

test('exam schedule separates upcoming and past exams and hides unpublished marks', function () {
    $this->travelTo('2026-09-16 09:00:00');
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $child = parentPortalStudent($tenant);
    $enrollment = enrollParentStudent($tenant, $parent, $child, $context);
    ['assessment' => $upcoming] = parentAssessment($tenant, $context, $enrollment, ['assessment_date' => '2026-09-20']);
    ['assessment' => $past] = parentAssessment($tenant, $context, $enrollment, ['assessment_date' => '2026-09-10']);
    ['assessment' => $unpublished] = parentAssessment($tenant, $context, $enrollment, ['name' => 'Résultat privé', 'assessment_date' => '2026-09-11', 'published_at' => null, 'published_by' => null]);

    $this->actingAs($user)->get('/parent/exams')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Parent/Exams')->where('upcoming.0.id', $upcoming->id)
        ->where('past.0.id', $unpublished->id)->where('past.0.result_published', false)->where('past.0.grade', null)
        ->where('past.1.id', $past->id)->where('past.1.grade', 16));
});

test('dashboard only uses the next exam and latest published grade for selected child', function () {
    $this->travelTo('2026-09-16 09:00:00');
    ['tenant' => $tenant, 'user' => $user, 'parent' => $parent] = parentPortalAccount();
    $context = parentAcademicContext($tenant);
    $child = parentPortalStudent($tenant);
    $enrollment = enrollParentStudent($tenant, $parent, $child, $context);
    ['assessment' => $next] = parentAssessment($tenant, $context, $enrollment, ['assessment_date' => '2026-09-18']);
    parentAssessment($tenant, $context, $enrollment, ['assessment_date' => '2026-09-25']);
    ['assessment' => $unpublished] = parentAssessment($tenant, $context, $enrollment, ['name' => 'Note secrète', 'assessment_date' => '2026-09-17', 'published_at' => null, 'published_by' => null]);

    $this->actingAs($user)->get('/parent')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('today.exam.name', $unpublished->name)->where('today.exam.date', '2026-09-17')
        ->where('today.grade.value', 16));
});
