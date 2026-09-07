<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\AcademicYearCalendarEvent;
use App\Models\Classroom;
use App\Models\RoomReservation;
use App\Models\SchoolCycle;
use App\Models\SchoolGroup;
use App\Models\SchoolLevel;
use App\Models\SchoolSite;
use App\Models\Student;
use App\Models\StudentAcademicEnrollment;
use App\Models\TeacherAcademicAssignment;
use App\Models\Tenant;
use App\Models\TimetableSession;
use App\Models\TimetableSetting;
use App\Models\User;
use App\Services\AlgerianCurriculum2026;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PrivateSchool2026DemoSeeder extends Seeder
{
    private const GROUPS = [
        ['code' => '4AP-A', 'level' => '4AP', 'name' => '4AP - Groupe A'],
        ['code' => '5AP-A', 'level' => '5AP', 'name' => '5AP - Groupe A'],
        ['code' => '2AM-A', 'level' => '2AM', 'name' => '2AM - Groupe A'],
        ['code' => '1AS-ST-A', 'level' => '1AS', 'specialization' => 'Tronc Commun Sciences et Technologie', 'name' => '1AS Sciences et Technologie - A'],
    ];

    private const FIRST_NAMES = ['Amine', 'Lina', 'Yanis', 'Meriem', 'Rayan', 'Inès', 'Ilyes', 'Sarah', 'Anis', 'Nour', 'Aymen', 'Kenza', 'Walid', 'Yasmine', 'Nassim', 'Aya'];

    private const LAST_NAMES = ['Benali', 'Mansouri', 'Brahimi', 'Saadi', 'Bouzid', 'Ferhat', 'Rahmani', 'Mokrani', 'Haddad', 'Meziane', 'Taleb', 'Cherif', 'Ammar', 'Hamidi', 'Bensalem', 'Dahmani'];

    public function run(): void
    {
        $context = app(TenantContext::class);
        Tenant::where('organization_type', 'private_school')->orderBy('id')->each(function (Tenant $tenant) use ($context): void {
            try {
                $context->set($tenant);
                DB::transaction(fn () => $this->seedTenant($tenant));
            } finally {
                $context->clear();
            }
        });
    }

    private function seedTenant(Tenant $tenant): void
    {
        $this->ensureLevels();
        app(AlgerianCurriculum2026::class)->initialize();
        AcademicYear::where('status', 'active')->where('name', '!=', '2026-2027')->update(['status' => 'draft']);
        $year = AcademicYear::updateOrCreate(['name' => '2026-2027'], [
            'start_date' => '2026-09-06', 'end_date' => '2027-06-30', 'status' => 'active',
            'notes' => 'Année scolaire algérienne 2026-2027.',
        ]);
        $this->seedPeriodsAndBreaks($year);
        TimetableSetting::updateOrCreate([], [
            'working_days' => [7, 1, 2, 3, 4], 'day_starts_at' => '08:00', 'day_ends_at' => '15:00',
            'default_session_duration' => 60, 'breaks' => [['name' => 'Déjeuner', 'start_time' => '12:00', 'end_time' => '13:00']],
            'time_slots' => [['start_time' => '08:00', 'end_time' => '09:00'], ['start_time' => '09:00', 'end_time' => '10:00'], ['start_time' => '10:00', 'end_time' => '11:00'], ['start_time' => '11:00', 'end_time' => '12:00'], ['start_time' => '13:00', 'end_time' => '14:00'], ['start_time' => '14:00', 'end_time' => '15:00']],
        ]);
        $site = SchoolSite::updateOrCreate(['code' => 'PRINCIPAL-PRIVE'], [
            'name' => 'École privée - Site principal', 'wilaya' => $tenant->wilaya ?: 'Alger', 'commune' => $tenant->commune ?: 'Alger Centre',
            'address' => $tenant->address ?: 'Alger', 'phone' => $tenant->phone, 'is_active' => true,
        ]);

        foreach (self::GROUPS as $groupIndex => $definition) {
            $level = SchoolLevel::where('code', $definition['level'])
                ->when(isset($definition['specialization']), fn ($query) => $query->where('specialization', $definition['specialization']), fn ($query) => $query->whereNull('specialization'))->firstOrFail();
            $room = Classroom::updateOrCreate(['code' => 'PS-'.($groupIndex + 1)], [
                'school_site_id' => $site->id, 'name' => 'Salle '.($groupIndex + 1), 'type' => 'classroom', 'capacity' => 24,
                'location' => ($groupIndex + 1).'e salle', 'is_active' => true, 'is_available' => true,
            ]);
            $group = SchoolGroup::updateOrCreate(['academic_year_id' => $year->id, 'code' => $definition['code']], [
                'school_level_id' => $level->id, 'classroom_id' => $room->id, 'name' => $definition['name'], 'capacity' => 24, 'is_active' => true,
            ]);
            $subjects = $level->subjects()->wherePivot('is_active', true)->orderByPivot('display_order')->get();
            $teachers = $this->seedTeachers($tenant, $year, $group, $level, $subjects, $groupIndex);
            $this->seedStudents($year, $group, $level, $groupIndex);
            $this->seedTimetable($year, $group, $room, $subjects, $teachers);
        }
    }

    private function ensureLevels(): void
    {
        $definitions = [
            ['Primaire', 'PRIMARY', ['1AP', '2AP', '3AP', '4AP', '5AP']],
            ['Moyen', 'CEM', ['1AM', '2AM', '3AM', '4AM']],
            ['Lycée', 'LYCEE', ['1AS', '2AS', '3AS']],
        ];
        foreach ($definitions as $cycleOrder => [$name, $code, $levels]) {
            $cycle = SchoolCycle::firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => $cycleOrder + 1, 'is_active' => true]);
            foreach ($levels as $order => $levelCode) {
                SchoolLevel::firstOrCreate(['code' => $levelCode, 'specialization' => null], [
                    'school_cycle_id' => $cycle->id, 'name' => $levelCode, 'sort_order' => $order + 1, 'is_active' => true,
                ]);
            }
        }
    }

    private function seedPeriodsAndBreaks(AcademicYear $year): void
    {
        foreach ([
            [1, 'Premier trimestre', '2026-09-06', '2026-12-17'],
            [2, 'Deuxième trimestre', '2027-01-03', '2027-03-18'],
            [3, 'Troisième trimestre', '2027-04-04', '2027-06-30'],
        ] as [$number, $name, $starts, $ends]) {
            AcademicPeriod::updateOrCreate(['academic_year_id' => $year->id, 'number' => $number], [
                'name' => $name, 'academic_year' => $year->name, 'starts_on' => $starts, 'ends_on' => $ends,
                'status' => $number === 1 ? 'active' : 'planned', 'is_current' => $number === 1,
            ]);
        }
        foreach ([
            ['Vacances d’hiver', 'winter_break', '2026-12-18', '2027-01-02'],
            ['Vacances de printemps', 'spring_break', '2027-03-19', '2027-04-03'],
        ] as [$name, $type, $starts, $ends]) {
            AcademicYearCalendarEvent::updateOrCreate(['academic_year_id' => $year->id, 'type' => $type, 'starts_on' => $starts], [
                'name' => $name, 'ends_on' => $ends, 'applies_to' => 'both', 'is_paid_for_teachers' => true,
            ]);
        }
    }

    private function seedTeachers(Tenant $tenant, AcademicYear $year, SchoolGroup $group, SchoolLevel $level, $subjects, int $groupIndex): array
    {
        $names = [['Nadia', 'Benkhelifa'], ['Karim', 'Mansouri'], ['Samira', 'Aït Ahmed'], ['Sofiane', 'Bouzid']];
        $teachers = [];
        foreach ($names as $index => [$first, $last]) {
            $number = $groupIndex * 4 + $index + 1;
            $teacher = User::updateOrCreate(['email' => "enseignant{$number}.tenant{$tenant->id}.2026@demo.ecole.test"], [
                'name' => "{$first} {$last}", 'phone' => '0551'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'), 'role' => UserRole::TEACHER, 'job_title' => 'Enseignant école privée',
                'is_active' => true, 'can_login' => true, 'email_verified_at' => now(),
            ]);
            $assigned = $subjects->values()->filter(fn ($subject, $subjectIndex) => $subjectIndex % 4 === $index);
            foreach ($assigned as $subject) {
                $subject->teachers()->syncWithoutDetaching([$teacher->id]);
                TeacherAcademicAssignment::updateOrCreate([
                    'academic_year_id' => $year->id, 'teacher_id' => $teacher->id, 'course_id' => $subject->id, 'school_group_id' => $group->id,
                ], ['school_level_id' => $level->id, 'is_active' => true]);
            }
            $group->teachers()->syncWithoutDetaching([$teacher->id]);
            $teachers[] = $teacher;
        }

        return $teachers;
    }

    private function seedStudents(AcademicYear $year, SchoolGroup $group, SchoolLevel $level, int $groupIndex): void
    {
        foreach (range(1, 20) as $position) {
            $number = $groupIndex * 20 + $position;
            $student = Student::updateOrCreate(['email' => "eleve{$number}.tenant{$group->tenant_id}.2026@demo.ecole.test"], [
                'first_name' => self::FIRST_NAMES[($number - 1) % count(self::FIRST_NAMES)],
                'last_name' => self::LAST_NAMES[(int) floor(($number - 1) / count(self::FIRST_NAMES)) % count(self::LAST_NAMES)],
                'phone' => '0560'.str_pad((string) $number, 6, '0', STR_PAD_LEFT), 'parent_phone' => '0770'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'birth_date' => now()->subYears(8 + $groupIndex * 2)->subDays($position * 7)->toDateString(),
                'registration_date' => '2026-09-06', 'school_level' => $level->name, 'school_group_id' => $group->id,
                'status' => 'active', 'is_active' => true, 'address' => 'Alger',
            ]);
            StudentAcademicEnrollment::updateOrCreate(['academic_year_id' => $year->id, 'student_id' => $student->id], [
                'school_level_id' => $level->id, 'school_group_id' => $group->id, 'status' => 'enrolled', 'enrollment_date' => '2026-09-06',
            ]);
        }
    }

    private function seedTimetable(AcademicYear $year, SchoolGroup $group, Classroom $room, $subjects, array $teachers): void
    {
        $slots = [['08:00', '09:00'], ['09:00', '10:00'], ['10:00', '11:00'], ['11:00', '12:00'], ['13:00', '14:00'], ['14:00', '15:00']];
        foreach ([7, 1, 2, 3, 4] as $dayIndex => $day) {
            foreach ($slots as $slotIndex => [$start, $end]) {
                $subjectIndex = ($dayIndex * count($slots) + $slotIndex) % $subjects->count();
                $subject = $subjects[$subjectIndex];
                $teacher = $teachers[$subjectIndex % count($teachers)];
                $session = TimetableSession::updateOrCreate([
                    'academic_year_id' => $year->id, 'school_group_id' => $group->id, 'day' => $day, 'start_time' => $start,
                ], ['course_id' => $subject->id, 'teacher_id' => $teacher->id, 'classroom_id' => $room->id, 'end_time' => $end,
                    'recurrence' => 'weekly', 'status' => 'published', 'notes' => 'Emploi du temps annuel 2026-2027.']);
                RoomReservation::updateOrCreate(['timetable_session_id' => $session->id], [
                    'classroom_id' => $room->id, 'day' => $day, 'start_time' => $start, 'end_time' => $end, 'status' => 'reserved',
                ]);
            }
        }
    }
}
