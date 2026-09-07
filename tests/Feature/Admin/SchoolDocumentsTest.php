<?php

use App\Enums\SchoolDocumentType;
use App\Models\AcademicYear;
use App\Models\CompanySetting;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SchoolDocumentGenerator;
use App\Tenancy\TenantContext;

function schoolDocumentsFixture(): array
{
    $tenant = Tenant::factory()->create(['organization_type' => 'private_school']);
    app(TenantContext::class)->set($tenant);
    $year = AcademicYear::create(['name' => '2026-2027', 'start_date' => '2026-09-01', 'end_date' => '2027-06-30', 'status' => 'active']);
    $cycle = SchoolCycle::create(['name' => 'Secondaire', 'code' => 'SEC-DOC', 'sort_order' => 1]);
    $level = SchoolLevel::create(['school_cycle_id' => $cycle->id, 'name' => '1AS', 'code' => '1AS-DOC', 'sort_order' => 1]);
    $firstGroup = SchoolGroup::create(['academic_year_id' => $year->id, 'school_level_id' => $level->id, 'name' => '1AS-A', 'code' => '1AS-A-DOC', 'capacity' => 30]);
    $secondGroup = SchoolGroup::create(['academic_year_id' => $year->id, 'school_level_id' => $level->id, 'name' => '1AS-B', 'code' => '1AS-B-DOC', 'capacity' => 30]);
    $student = Student::create(['first_name' => 'Amine', 'last_name' => 'Benali', 'phone' => '0550000000', 'birth_date' => '2010-04-05', 'registration_date' => '2026-09-01', 'status' => 'active', 'is_active' => true]);
    StudentAcademicEnrollment::create(['academic_year_id' => $year->id, 'student_id' => $student->id, 'school_level_id' => $level->id, 'school_group_id' => $firstGroup->id, 'status' => 'enrolled', 'enrollment_date' => '2026-09-01']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    app(TenantContext::class)->clear();

    return compact('tenant', 'admin', 'year', 'level', 'firstGroup', 'secondGroup', 'student');
}

test('private school admin can open the school documents module', function () {
    $fixture = schoolDocumentsFixture();

    $this->actingAs($fixture['admin'])->get('/admin/school-documents')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/SchoolDocuments/Index')
        ->where('academicYear.id', $fixture['year']->id)
        ->has('documentTypes', 1)
        ->has('groups', 2)
        ->where('groups.0.students_count', 1));
});

test('school document download validates the selection', function () {
    $fixture = schoolDocumentsFixture();

    $this->actingAs($fixture['admin'])->post('/admin/school-documents/download', [
        'document_type' => 'school_certificate',
        'language' => 'de',
        'issue_date' => '2026-09-07',
        'group_ids' => [],
    ])->assertSessionHasErrors(['language', 'group_ids']);
});

test('school certificate renders in french and arabic', function () {
    $fixture = schoolDocumentsFixture();
    app(TenantContext::class)->set($fixture['tenant']);
    $enrollment = StudentAcademicEnrollment::with(['student', 'academicYear', 'level', 'stream', 'group'])->firstOrFail();
    $generator = app(SchoolDocumentGenerator::class);

    foreach (['fr', 'ar'] as $language) {
        $pdf = $generator->pdf(SchoolDocumentType::SCHOOL_CERTIFICATE, $enrollment, CompanySetting::current(), $language, '2026-09-07');
        expect($pdf)->toStartWith('%PDF-')->and(strlen($pdf))->toBeGreaterThan(1000);
    }
});

test('school certificates download as a zip with one pdf per enrolled student', function () {
    $fixture = schoolDocumentsFixture();

    $response = $this->actingAs($fixture['admin'])->post('/admin/school-documents/download', [
        'document_type' => 'school_certificate',
        'language' => 'ar',
        'issue_date' => '2026-09-07',
        'group_ids' => [$fixture['firstGroup']->id, $fixture['secondGroup']->id],
    ]);

    $response->assertOk()->assertDownload('certificats-scolarite-2026-2027-arabe.zip');
    $zip = new PharData($response->baseResponse->getFile()->getPathname());
    $files = iterator_to_array(new RecursiveIteratorIterator($zip));
    expect($files)->toHaveCount(1)
        ->and(array_key_first($files))->toContain('1AS-A/')->toEndWith('.pdf')
        ->and(file_get_contents(array_values($files)[0]->getPathname()))->toStartWith('%PDF-');
});
