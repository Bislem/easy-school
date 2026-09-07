<?php

namespace App\Services;

use App\Models\SchoolLevel;
use App\Models\SchoolSubject;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AlgerianCurriculum2026
{
    public const CODE = 'DZ_2026_2027';

    private const SUBJECTS = [
        'AR' => ['Langue arabe', 'اللغة العربية'], 'ARL' => ['Langue et littérature arabes', 'اللغة العربية وآدابها'],
        'MATH' => ['Mathématiques', 'الرياضيات'], 'ISLAM' => ['Éducation islamique', 'التربية الإسلامية'], 'ISLAMSCI' => ['Sciences islamiques', 'العلوم الإسلامية'],
        'ARTED' => ['Éducation artistique', 'التربية الفنية'], 'PE' => ['Éducation physique', 'التربية البدنية'], 'PES' => ['Éducation physique et sportive', 'التربية البدنية والرياضية'],
        'EN' => ['Langue anglaise', 'اللغة الإنجليزية'], 'EN2' => ['Anglais', 'اللغة الإنجليزية'], 'FR' => ['Langue française', 'اللغة الفرنسية'], 'FR2' => ['Français', 'اللغة الفرنسية'], 'AMZ' => ['Langue amazighe', 'اللغة الأمازيغية'],
        'SCI_TECH' => ['Éducation scientifique et technologique', 'التربية العلمية والتكنولوجية'], 'CIVIC' => ['Éducation civique', 'التربية المدنية'],
        'HISTGEO' => ['Histoire et Géographie', 'التاريخ والجغرافيا'], 'HIST' => ['Histoire', 'التاريخ'], 'SNV' => ['Sciences de la nature et de la vie', 'علوم الطبيعة والحياة'],
        'PHYSTECH' => ['Sciences physiques et technologie', 'العلوم الفيزيائية والتكنولوجيا'], 'PHYS' => ['Sciences physiques', 'العلوم الفيزيائية'],
        'INFO' => ['Informatique', 'الإعلام الآلي'], 'PLASTIC' => ['Arts plastiques', 'التربية التشكيلية'], 'MUSIC' => ['Éducation musicale', 'التربية الموسيقية'],
        'TECH' => ['Technologie', 'التكنولوجيا'], 'PHILO' => ['Philosophie', 'الفلسفة'], 'ART1' => ['Arts 1', 'فنون 1'], 'ART2' => ['Arts 2', 'فنون 2'],
        'ACCOUNT' => ['Gestion comptable et financière', 'التسيير المحاسبي والمالي'], 'ECON' => ['Économie et Management', 'الاقتصاد والمناجمنت'], 'LAW' => ['Droit', 'القانون'],
        'ES' => ['Espagnol', 'اللغة الإسبانية'], 'DE' => ['Allemand', 'اللغة الألمانية'], 'IT' => ['Italien', 'اللغة الإيطالية'],
    ];

    public const LYCEE_SPECIALIZATIONS = [
        'TC_LETTRES' => ['Tronc Commun Lettres', 'جذع مشترك آداب'], 'TC_SCI_TECH' => ['Tronc Commun Sciences et Technologie', 'جذع مشترك علوم وتكنولوجيا'],
        'LETTRES_PHILO' => ['Lettres et Philosophie', 'آداب وفلسفة'], 'LANGUES' => ['Langues Étrangères', 'لغات أجنبية'], 'ARTS' => ['Arts', 'فنون'],
        'MATHEMATIQUES' => ['Mathématiques', 'رياضيات'], 'SCIENCES_EXP' => ['Sciences Expérimentales', 'علوم تجريبية'],
        'GESTION_ECO' => ['Gestion et Économie', 'تسيير واقتصاد'], 'GENIE' => ['Génie', 'هندسة'],
    ];

    public function initialize(): array
    {
        $tenantId = app(TenantContext::class)->id();
        if (! $tenantId) {
            throw ValidationException::withMessages(['curriculum' => 'A tenant context is required.']);
        }

        return DB::transaction(function () use ($tenantId): array {
            $courses = collect(self::SUBJECTS)->mapWithKeys(function ($labels, $key) {
                return [$key => SchoolSubject::firstOrCreate(['code' => 'DZ-'.$key], [
                    'title' => $labels[0], 'title_ar' => $labels[1], 'category' => 'Programme national algérien',
                    'color' => $this->color($key), 'duration_hours' => 1, 'weekly_hours' => 1,
                    'price' => 0, 'is_certified' => false, 'is_specialized' => false, 'is_active' => true,
                ])];
            });

            $created = 0;
            foreach ($this->assignments() as [$levelCode, $streamCode, $subjects]) {
                $specialization = $streamCode ? self::LYCEE_SPECIALIZATIONS[$streamCode][0] : null;
                $level = $specialization
                    ? $this->specializedLyceeLevel($levelCode, $specialization)
                    : SchoolLevel::where('code', $levelCode)->whereNull('specialization')->first();
                if (! $level) {
                    continue;
                }
                foreach ($subjects as $order => $definition) {
                    [$key, $optional, $choice] = array_pad(explode(':', $definition, 3), 3, null);
                    $identity = ['course_id' => $courses[$key]->id, 'school_level_id' => $level->id, 'curriculum_code' => self::CODE];
                    if (! DB::table('course_school_level')->where($identity)->exists()) {
                        DB::table('course_school_level')->insert([...$identity,
                            'tenant_id' => $tenantId, 'school_stream_id' => null, 'is_optional' => $optional === 'optional', 'is_active' => $optional !== 'optional',
                            'display_order' => $order + 1, 'choice_group' => $choice, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $created++;
                    }
                }
            }
            SchoolLevel::whereIn('code', ['1AS', '2AS', '3AS'])
                ->whereNull('specialization')->whereDoesntHave('groups')->update(['is_active' => false]);

            return ['created_assignments' => $created, 'curriculum_code' => self::CODE];
        });
    }

    private function specializedLyceeLevel(string $code, string $specialization): SchoolLevel
    {
        $base = SchoolLevel::where('code', $code)->whereNull('specialization')->first();
        if (! $base) {
            throw ValidationException::withMessages(['curriculum' => "Le niveau de base {$code} est introuvable."]);
        }

        return SchoolLevel::firstOrCreate(
            ['code' => $code, 'specialization' => $specialization],
            [
                'school_cycle_id' => $base->school_cycle_id,
                'name' => $code,
                'sort_order' => $base->sort_order * 10 + (int) collect(array_keys(self::LYCEE_SPECIALIZATIONS))->search(
                    fn (string $streamCode) => self::LYCEE_SPECIALIZATIONS[$streamCode][0] === $specialization
                ) + 1,
                'is_active' => true,
            ]
        );
    }

    private function assignments(): array
    {
        $primary = [
            ['1AP', null, ['AR', 'MATH', 'ISLAM', 'ARTED', 'PE']], ['2AP', null, ['AR', 'MATH', 'ISLAM', 'ARTED', 'PE']],
            ['3AP', null, ['AR', 'EN', 'MATH', 'SCI_TECH', 'ISLAM', 'HISTGEO', 'ARTED', 'PE']],
            ['4AP', null, ['AR', 'AMZ:optional', 'EN', 'FR', 'MATH', 'SCI_TECH', 'ISLAM', 'CIVIC', 'HISTGEO', 'ARTED', 'PE']],
            ['5AP', null, ['AR', 'AMZ:optional', 'EN', 'FR', 'MATH', 'SCI_TECH', 'ISLAM', 'CIVIC', 'HISTGEO', 'ARTED', 'PE']],
        ];
        $cemSubjects = ['AR', 'AMZ:optional', 'FR', 'EN', 'MATH', 'SNV', 'PHYSTECH', 'HISTGEO', 'ISLAM', 'CIVIC', 'INFO', 'PES', 'PLASTIC:optional', 'MUSIC:optional'];
        $cem = array_map(fn ($level) => [$level, null, $cemSubjects], ['1AM', '2AM', '3AM', '4AM']);
        $lycee = [
            ['1AS', 'TC_LETTRES', ['ARL', 'EN2', 'FR2', 'ISLAMSCI', 'HISTGEO', 'MATH', 'PHYS', 'SNV', 'INFO', 'PES', 'ARTED', 'AMZ:optional']],
            ['1AS', 'TC_SCI_TECH', ['ARL', 'EN2', 'FR2', 'ISLAMSCI', 'HISTGEO', 'MATH', 'PHYS', 'SNV', 'TECH', 'INFO', 'PES', 'ARTED', 'AMZ:optional']],
            ['2AS', 'LETTRES_PHILO', ['AR', 'PHILO', 'HISTGEO', 'AMZ:optional', 'EN2', 'FR2', 'INFO', 'MATH', 'SNV', 'ISLAMSCI', 'PES', 'ARTED']],
            ['3AS', 'LETTRES_PHILO', ['ARL', 'PHILO', 'HISTGEO', 'AMZ:optional', 'EN2', 'FR2', 'ISLAMSCI', 'PES']],
            ['2AS', 'LANGUES', ['AR', 'EN2', 'FR2', 'PHILO', 'HISTGEO', 'INFO', 'MATH', 'ISLAMSCI', 'PES', 'ARTED', 'AMZ:optional', 'ES:optional:THIRD_FOREIGN_LANGUAGE', 'DE:optional:THIRD_FOREIGN_LANGUAGE', 'IT:optional:THIRD_FOREIGN_LANGUAGE']],
            ['3AS', 'LANGUES', ['AR', 'EN2', 'FR2', 'HISTGEO', 'ISLAMSCI', 'PES', 'AMZ:optional', 'ES:optional:THIRD_FOREIGN_LANGUAGE', 'DE:optional:THIRD_FOREIGN_LANGUAGE', 'IT:optional:THIRD_FOREIGN_LANGUAGE']],
            ['2AS', 'ARTS', ['ART1', 'ART2', 'AR', 'AMZ:optional', 'EN2', 'FR2', 'PHILO', 'HISTGEO', 'INFO', 'MATH', 'PHYS', 'ISLAMSCI', 'PES']],
            ['3AS', 'ARTS', ['ART1', 'ART2', 'AR', 'AMZ:optional', 'EN2', 'FR2', 'HISTGEO', 'ISLAMSCI', 'PES']],
            ['2AS', 'MATHEMATIQUES', ['MATH', 'PHYS', 'AR', 'AMZ:optional', 'INFO', 'SNV', 'EN2', 'PHILO', 'HISTGEO', 'ISLAMSCI', 'FR2', 'PES', 'ARTED']],
            ['3AS', 'MATHEMATIQUES', ['MATH', 'PHYS', 'INFO', 'SNV', 'EN2', 'HIST', 'ISLAMSCI', 'PES']],
            ['2AS', 'SCIENCES_EXP', ['SNV', 'MATH', 'PHYS', 'AR', 'AMZ:optional', 'INFO', 'EN2', 'PHILO', 'HISTGEO', 'ISLAMSCI', 'FR2', 'PES', 'ARTED']],
            ['3AS', 'SCIENCES_EXP', ['SNV', 'MATH', 'PHYS', 'AR', 'AMZ:optional', 'EN2', 'HIST', 'ISLAMSCI', 'PES']],
            ['2AS', 'GESTION_ECO', ['ACCOUNT', 'ECON', 'MATH', 'AR', 'AMZ:optional', 'INFO', 'EN2', 'PHILO', 'LAW', 'HISTGEO', 'ISLAMSCI', 'FR2', 'PES', 'ARTED']],
            ['3AS', 'GESTION_ECO', ['ACCOUNT', 'ECON', 'MATH', 'AR', 'AMZ:optional', 'EN2', 'LAW', 'HISTGEO', 'ISLAMSCI', 'PES']],
            ['2AS', 'GENIE', ['TECH', 'MATH', 'PHYS', 'AR', 'AMZ:optional', 'INFO', 'EN2', 'PHILO', 'HISTGEO', 'ISLAMSCI', 'FR2', 'PES', 'ARTED']],
            ['3AS', 'GENIE', ['TECH', 'MATH', 'PHYS', 'INFO', 'EN2', 'HIST', 'ISLAMSCI', 'PES']],
        ];

        return [...$primary, ...$cem, ...$lycee];
    }

    private function color(string $key): string
    {
        return match ($key) {
            'MATH', 'PHYS', 'PHYSTECH', 'SNV', 'SCI_TECH' => '#2563eb', 'AR', 'ARL', 'FR', 'FR2', 'EN', 'EN2', 'AMZ', 'ES', 'DE', 'IT' => '#7c3aed', 'PE', 'PES' => '#059669', default => '#d97706'
        };
    }
}
