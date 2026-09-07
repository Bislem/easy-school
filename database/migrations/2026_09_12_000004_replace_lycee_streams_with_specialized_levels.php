<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SPECIALIZATIONS = [
        'TC_LETTRES' => ['1AS', 'Tronc Commun Lettres'],
        'TC_SCI_TECH' => ['1AS', 'Tronc Commun Sciences et Technologie'],
        'LETTRES_PHILO' => [['2AS', '3AS'], 'Lettres et Philosophie'],
        'LANGUES' => [['2AS', '3AS'], 'Langues Étrangères'],
        'ARTS' => [['2AS', '3AS'], 'Arts'],
        'MATHEMATIQUES' => [['2AS', '3AS'], 'Mathématiques'],
        'SCIENCES_EXP' => [['2AS', '3AS'], 'Sciences Expérimentales'],
        'GESTION_ECO' => [['2AS', '3AS'], 'Gestion et Économie'],
        'GENIE' => [['2AS', '3AS'], 'Génie'],
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('tenants')->pluck('id') as $tenantId) {
                foreach (self::SPECIALIZATIONS as $streamCode => [$codes, $specialization]) {
                    foreach ((array) $codes as $code) {
                        $base = DB::table('school_levels')->where('tenant_id', $tenantId)
                            ->where('code', $code)->whereNull('specialization')->first();
                        if (! $base) {
                            continue;
                        }

                        $levelId = DB::table('school_levels')->where('tenant_id', $tenantId)
                            ->where('code', $code)->where('specialization', $specialization)->value('id');
                        if (! $levelId) {
                            $levelId = DB::table('school_levels')->insertGetId([
                                'tenant_id' => $tenantId,
                                'school_cycle_id' => $base->school_cycle_id,
                                'name' => $code,
                                'code' => $code,
                                'specialization' => $specialization,
                                'sort_order' => ((int) $base->sort_order * 10) + 1,
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        $streamId = DB::table('school_streams')->where('tenant_id', $tenantId)
                            ->where('code', $streamCode)->value('id');
                        if (! $streamId) {
                            continue;
                        }

                        DB::table('course_school_level')->where('tenant_id', $tenantId)
                            ->where('school_level_id', $base->id)->where('school_stream_id', $streamId)
                            ->update(['school_level_id' => $levelId, 'school_stream_id' => null, 'updated_at' => now()]);

                        foreach (['school_groups', 'student_academic_enrollments', 'teacher_academic_assignments'] as $table) {
                            DB::table($table)->where('tenant_id', $tenantId)
                                ->where('school_level_id', $base->id)->where('school_stream_id', $streamId)
                                ->update(['school_level_id' => $levelId, 'school_stream_id' => null, 'updated_at' => now()]);
                        }
                    }
                }

                DB::table('school_levels')->where('tenant_id', $tenantId)
                    ->whereIn('code', ['1AS', '2AS', '3AS'])->whereNull('specialization')
                    ->whereNotExists(function ($query) {
                        $query->selectRaw('1')->from('school_groups')
                            ->whereColumn('school_groups.school_level_id', 'school_levels.id');
                    })->update(['is_active' => false, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        DB::table('school_levels')->whereIn('code', ['1AS', '2AS', '3AS'])
            ->whereNull('specialization')->update(['is_active' => true, 'updated_at' => now()]);
    }
};
