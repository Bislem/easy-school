<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('entity_type', 20)->default('formation')->after('id')->index();
        });
        Schema::table('courses', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'code']));

        DB::transaction(function (): void {
            $schoolIds = collect()
                ->merge(DB::table('course_school_level')->pluck('course_id'))
                ->merge(DB::table('timetable_sessions')->pluck('course_id'))
                ->merge(DB::table('teacher_academic_assignments')->pluck('course_id'))
                ->map(fn ($id) => (int) $id)->unique();
            $formationIds = collect()
                ->merge(DB::table('course_levels')->pluck('course_id'))
                ->merge(DB::table('enrollment_forms')->pluck('course_id'))
                ->merge(DB::table('certificates')->whereNotNull('course_id')->pluck('course_id'))
                ->map(fn ($id) => (int) $id)->unique();

            foreach ($schoolIds->intersect($formationIds) as $courseId) {
                $course = DB::table('courses')->where('id', $courseId)->first();
                if (! $course) {
                    continue;
                }
                $copy = (array) $course;
                unset($copy['id']);
                $copy['entity_type'] = 'subject';
                $copy['updated_at'] = now();
                $subjectId = DB::table('courses')->insertGetId($copy);

                DB::table('course_school_level')->where('course_id', $courseId)->update(['course_id' => $subjectId]);
                DB::table('timetable_sessions')->where('course_id', $courseId)->update(['course_id' => $subjectId]);
                DB::table('teacher_academic_assignments')->where('course_id', $courseId)->update(['course_id' => $subjectId]);
                DB::table('course_teacher')->where('course_id', $courseId)->get()->each(function ($teacher) use ($subjectId): void {
                    DB::table('course_teacher')->insertOrIgnore([
                        'tenant_id' => $teacher->tenant_id,
                        'course_id' => $subjectId,
                        'teacher_id' => $teacher->teacher_id,
                        'created_at' => $teacher->created_at,
                        'updated_at' => $teacher->updated_at,
                    ]);
                });
            }

            DB::table('courses')->whereIn('id', $schoolIds->diff($formationIds)->all())->update(['entity_type' => 'subject']);
        });

        Schema::table('courses', fn (Blueprint $table) => $table->unique(['tenant_id', 'entity_type', 'code']));
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'entity_type', 'code']);
            $table->dropColumn('entity_type');
            $table->index(['tenant_id', 'code']);
        });
    }
};
