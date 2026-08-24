<?php

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanGroup;
use App\Models\TrainingSession;
use Database\Seeders\SchoolDemoSeeder;

test('the school demo seeder creates a coherent working school without duplicates', function () {
    $this->seed(SchoolDemoSeeder::class);
    $this->seed(SchoolDemoSeeder::class);

    expect(Course::whereIn('code', ['WEB-FS', 'DES-GR', 'MKT-DIG', 'ENG-B1'])->count())->toBe(4)
        ->and(CourseLevel::where('code', 'GENERAL')->count())->toBe(4)
        ->and(SchoolSite::where('code', 'PRINCIPAL')->count())->toBe(1)
        ->and(Student::where('email', 'like', '%@demo.ecole.test')->count())->toBe(300)
        ->and(EnrollmentForm::whereIn('course_id', Course::whereIn('code', ['WEB-FS', 'DES-GR', 'MKT-DIG', 'ENG-B1'])->pluck('id'))->count())->toBe(4)
        ->and(CourseEnrollment::where('email', 'like', '%@demo.ecole.test')->count())->toBe(40)
        ->and(TrainingPlan::where('notes', 'Planification pédagogique de démonstration.')->count())->toBe(4)
        ->and(TrainingPlanGroup::whereHas('plan', fn ($query) => $query->where('notes', 'Planification pédagogique de démonstration.'))->count())->toBe(8)
        ->and(TrainingSession::whereHas('group.plan', fn ($query) => $query->where('notes', 'Planification pédagogique de démonstration.'))->count())->toBe(32);
});
