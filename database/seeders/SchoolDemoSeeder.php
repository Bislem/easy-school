<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\EnrollmentForm;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\TrainingPlan;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SchoolDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $teachers = $this->seedStaff();
            $site = $this->seedSchoolSite();
            $rooms = $this->seedClassrooms($site);
            $courses = $this->seedCourses();
            $students = $this->seedStudents();

            $definitions = [
                'WEB-FS' => [
                    'title' => 'Session Développement Web — Automne 2026',
                    'teacher' => 'yacine.benali@easyschool.test',
                    'start' => '2026-07-20', 'end' => '2026-10-30',
                    'rooms' => ['INFO-A', 'INFO-B'], 'dates' => ['2026-08-03', '2026-08-05', '2026-08-10', '2026-08-12'],
                    'times' => [['09:00', '12:00'], ['13:00', '16:00']],
                ],
                'DES-GR' => [
                    'title' => 'Session Design Graphique — Été 2026',
                    'teacher' => 'sonia.khelifi@easyschool.test',
                    'start' => '2026-08-01', 'end' => '2026-09-30',
                    'rooms' => ['CREA-1', 'POLY-1'], 'dates' => ['2026-08-04', '2026-08-06', '2026-08-11', '2026-08-13'],
                    'times' => [['09:00', '12:00'], ['13:00', '16:00']],
                ],
                'ENG-B1' => [
                    'title' => 'Anglais Professionnel B1 — Été 2026',
                    'teacher' => 'amel.rahmani@easyschool.test',
                    'start' => '2026-08-05', 'end' => '2026-10-05',
                    'rooms' => ['INFO-A', 'INFO-B'], 'dates' => ['2026-08-07', '2026-08-08', '2026-08-14', '2026-08-15'],
                    'times' => [['09:00', '11:00'], ['13:00', '15:00']],
                ],
                'MKT-DIG' => [
                    'title' => 'Marketing Digital — Rentrée 2026',
                    'teacher' => 'mehdi.aitali@easyschool.test',
                    'start' => '2026-08-24', 'end' => '2026-10-24',
                    'rooms' => ['CREA-1', 'POLY-1'], 'dates' => ['2026-08-24', '2026-08-26', '2026-08-31', '2026-09-02'],
                    'times' => [['09:00', '12:00'], ['13:00', '16:00']],
                ],
            ];

            foreach (array_values($definitions) as $courseIndex => $definition) {
                $course = $courses[$this->courseCodeAt($definitions, $courseIndex)];
                $teacher = $teachers[$definition['teacher']];
                $form = EnrollmentForm::updateOrCreate(
                    ['title' => $definition['title']],
                    [
                        'course_id' => $course->id, 'teacher_id' => $teacher->id,
                        'classroom_id' => null, 'start_date' => $definition['start'], 'end_date' => $definition['end'],
                        'min_students' => 6, 'max_students' => 16, 'groups_count' => 2,
                        'students_per_group' => 8, 'is_active' => true,
                    ],
                );

                $assignedStudents = $students->slice($courseIndex * 10, 10)->values();
                foreach ($assignedStudents as $index => $student) {
                    $enrollment = CourseEnrollment::firstOrNew(['enrollment_form_id' => $form->id, 'email' => $student->email]);
                    $enrollment->fill([
                        'student_id' => $student->id, 'first_name' => $student->first_name, 'last_name' => $student->last_name,
                        'phone' => $student->phone, 'birth_date' => $student->birth_date,
                        'confirmed_at' => now()->subDays(35 - ($courseIndex * 6 + $index)),
                        'group_number' => ($index % 2) + 1,
                    ]);
                    $enrollment->confirmation_token ??= (string) Str::uuid();
                    $enrollment->save();
                }

                $this->seedPlan($form, $course, $teacher, $rooms, $definition);
            }
        });
    }

    private function seedStaff(): array
    {
        $password = Hash::make('password');
        $staff = [
            ['Yacine Benali', 'yacine.benali@easyschool.test', '0550 10 10 01', UserRole::TEACHER, 'Formateur Développement Web', true],
            ['Sonia Khelifi', 'sonia.khelifi@easyschool.test', '0550 10 10 02', UserRole::TEACHER, 'Formatrice Design Graphique', true],
            ['Mehdi Aït Ali', 'mehdi.aitali@easyschool.test', '0550 10 10 03', UserRole::TEACHER, 'Formateur Marketing Digital', true],
            ['Amel Rahmani', 'amel.rahmani@easyschool.test', '0550 10 10 04', UserRole::TEACHER, 'Formatrice Langues', true],
            ['Nadia Merabet', 'nadia.merabet@easyschool.test', '0550 10 10 05', UserRole::EMPLOYEE, 'Secrétaire pédagogique', false],
            ['Karim Haddad', 'karim.haddad@easyschool.test', '0550 10 10 06', UserRole::EMPLOYEE, 'Comptable', false],
        ];
        $result = [];
        foreach ($staff as [$name, $email, $phone, $role, $job, $canLogin]) {
            $result[$email] = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'phone' => $phone, 'password' => $password, 'role' => $role,
                'job_title' => $job, 'is_active' => true, 'can_login' => $canLogin, 'email_verified_at' => now(),
            ]);
        }
        return $result;
    }

    private function seedSchoolSite(): SchoolSite
    {
        return SchoolSite::updateOrCreate(
            ['code' => 'PRINCIPAL'],
            [
                'name' => 'Site principal',
                'wilaya' => 'Béjaïa',
                'commune' => 'Béjaïa',
                'address' => 'Centre-ville, Béjaïa',
                'phone' => '034 00 00 00',
                'is_active' => true,
            ],
        );
    }

    private function seedClassrooms(SchoolSite $site): array
    {
        $definitions = [
            ['INFO-A', 'Salle Informatique A', 18, '1er étage'], ['INFO-B', 'Salle Informatique B', 18, '1er étage'],
            ['CREA-1', 'Atelier Créatif', 16, 'Rez-de-chaussée'], ['POLY-1', 'Salle Polyvalente', 24, '2e étage'],
            ['CONF', 'Salle de Conférence', 40, 'Rez-de-chaussée'],
        ];
        $rooms = [];
        foreach ($definitions as [$code, $name, $capacity, $location]) {
            $rooms[$code] = Classroom::updateOrCreate(['code' => $code], [
                'school_site_id' => $site->id, 'name' => $name, 'capacity' => $capacity, 'location' => $location,
                'description' => 'Salle équipée pour les activités pédagogiques.', 'is_active' => true,
            ]);
        }
        return $rooms;
    }

    private function seedCourses(): array
    {
        $definitions = [
            ['WEB-FS', 'Développement Web Full Stack', 'Informatique', 72, 45000, true],
            ['DES-GR', 'Design Graphique', 'Création', 48, 32000, true],
            ['MKT-DIG', 'Marketing Digital', 'Marketing', 54, 38000, true],
            ['ENG-B1', 'Anglais Professionnel B1', 'Langues', 36, 24000, false],
        ];
        $courses = [];
        foreach ($definitions as [$code, $title, $category, $duration, $price, $certified]) {
            $courses[$code] = Course::updateOrCreate(['code' => $code], [
                'title' => $title, 'category' => $category, 'duration_hours' => $duration, 'price' => $price,
                'description' => "Formation pratique en {$title} avec projets et accompagnement personnalisé.",
                'objectives' => 'Acquérir des compétences directement applicables en contexte professionnel.',
                'prerequisites' => 'Motivation et connaissances de base.', 'is_certified' => $certified, 'is_active' => true,
            ]);
        }
        return $courses;
    }

    private function seedStudents()
    {
        $firstNames = ['Lina', 'Amine', 'Sarah', 'Yanis', 'Inès', 'Rayan', 'Mélissa', 'Walid', 'Nour', 'Ilyes', 'Meriem', 'Samy', 'Lydia', 'Aymen', 'Nesrine', 'Farid', 'Kenza', 'Anis', 'Sabrina', 'Sofiane', 'Imane', 'Nassim', 'Yasmine', 'Massinissa', 'Aya', 'Islam', 'Célia', 'Mohamed', 'Lamia', 'Bilal', 'Tinhinane', 'Adel', 'Rima', 'Hakim', 'Manel', 'Zinedine', 'Naïma', 'Khaled', 'Selma', 'Lounès'];
        $lastNames = ['Brahimi', 'Saadi', 'Mansouri', 'Bouzid', 'Hamidi', 'Mokrani', 'Ferhat', 'Dahmani', 'Ammar', 'Taleb', 'Benali', 'Khelifi', 'Rahmani', 'Haddad', 'Bensalem', 'Meziane', 'Cherif', 'Aït Ali'];
        $cities = ['Alger', 'Oran', 'Constantine', 'Béjaïa', 'Sétif', 'Tlemcen', 'Annaba', 'Blida', 'Boumerdès', 'Tizi Ouzou', 'Akbou', 'Batna'];
        $levels = ['Collège', 'Lycée', 'Bac', 'Licence', 'Master', 'Formation professionnelle'];
        $statuses = ['active', 'active', 'active', 'enrolled', 'waiting', 'completed', 'stopped'];
        $faker = Faker::create('fr_FR');
        $faker->seed(20260824);

        return collect(range(1, 300))->map(function (int $number) use ($faker, $firstNames, $lastNames, $cities, $levels, $statuses) {
            $index = $number - 1;
            $firstName = $firstNames[$faker->numberBetween(0, count($firstNames) - 1)];
            $status = $statuses[$faker->numberBetween(0, count($statuses) - 1)];

            $number = $index + 1;
            return Student::updateOrCreate(['email' => "apprenant{$number}@demo.ecole.test"], [
                'first_name' => $firstName,
                'last_name' => $lastNames[$faker->numberBetween(0, count($lastNames) - 1)],
                'phone' => '0'.$faker->randomElement(['5', '6', '7']).$faker->numerify('## ## ## ##'),
                'parent_phone' => $faker->boolean(65) ? '0'.$faker->randomElement(['5', '6', '7']).$faker->numerify('## ## ## ##') : null,
                'birth_date' => $faker->dateTimeBetween('-42 years', '-15 years')->format('Y-m-d'),
                'registration_date' => $faker->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
                'school_level' => $levels[$faker->numberBetween(0, count($levels) - 1)],
                'address' => $cities[$faker->numberBetween(0, count($cities) - 1)],
                'status' => $status,
                'notes' => $faker->boolean(12) ? 'Dossier administratif à compléter.' : null,
                'is_active' => ! in_array($status, ['stopped', 'completed'], true),
            ]);
        });
    }

    private function seedPlan(EnrollmentForm $form, Course $course, User $teacher, array $rooms, array $definition): void
    {
        $level = $course->levels()->updateOrCreate(
            ['code' => 'GENERAL'],
            [
                'name' => 'Niveau général',
                'duration_hours' => $course->duration_hours,
                'price' => $course->price,
                'prerequisites' => $course->prerequisites,
                'is_active' => $course->is_active,
            ],
        );

        $plan = TrainingPlan::updateOrCreate(['title' => 'Planification — '.$definition['title']], [
            'course_level_id' => $level->id, 'enrollment_form_id' => $form->id, 'teacher_id' => $teacher->id,
            'status' => $definition['start'] > today()->toDateString() ? 'scheduled' : 'in_progress',
            'notes' => 'Planification pédagogique de démonstration.',
        ]);
        foreach ([1, 2] as $groupNumber) {
            $room = $rooms[$definition['rooms'][$groupNumber - 1]];
            $group = $plan->groups()->updateOrCreate(['group_number' => $groupNumber], [
                'name' => "Groupe {$groupNumber}", 'classroom_id' => $room->id, 'capacity' => min(8, $room->capacity),
            ]);
            $group->sessions()->delete();
            foreach ($definition['dates'] as $sessionIndex => $date) {
                [$start, $end] = $definition['times'][$groupNumber - 1];
                $group->sessions()->create([
                    'classroom_id' => $room->id, 'teacher_id' => $teacher->id,
                    'title' => $course->title.' — Séance '.($sessionIndex + 1),
                    'starts_at' => "{$date} {$start}:00", 'ends_at' => "{$date} {$end}:00",
                    'notes' => $sessionIndex === 0 ? 'Présentation du programme et objectifs.' : null,
                ]);
            }
        }
    }

    private function courseCodeAt(array $definitions, int $index): string
    {
        return array_keys($definitions)[$index];
    }
}
