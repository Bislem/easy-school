<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('name_fr');
            $table->string('name_ar')->nullable();
            $table->string('curriculum_code', 40)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::disableForeignKeyConstraints();
            Schema::create('course_school_level_v2', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_level_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_stream_id')->nullable()->constrained('school_streams')->cascadeOnDelete();
                $table->string('curriculum_code', 40)->nullable()->index();
                $table->boolean('is_optional')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedSmallInteger('display_order')->default(0);
                $table->string('choice_group', 80)->nullable();
                $table->timestamps();
                $table->index(['course_id', 'school_level_id', 'school_stream_id'], 'subject_level_stream_idx');
            });
            DB::table('course_school_level')->orderBy('course_id')->each(fn ($assignment) => DB::table('course_school_level_v2')->insert([
                'tenant_id' => $assignment->tenant_id, 'course_id' => $assignment->course_id,
                'school_level_id' => $assignment->school_level_id, 'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ]));
            Schema::drop('course_school_level');
            Schema::rename('course_school_level_v2', 'course_school_level');
            Schema::enableForeignKeyConstraints();

            return;
        }

        Schema::table('course_school_level', function (Blueprint $table) {
            $table->dropPrimary(['course_id', 'school_level_id']);
        });
        Schema::table('course_school_level', function (Blueprint $table) {
            $table->id()->first();
            $table->foreignId('school_stream_id')->nullable()->after('school_level_id')->constrained('school_streams')->cascadeOnDelete();
            $table->string('curriculum_code', 40)->nullable()->after('school_stream_id')->index();
            $table->boolean('is_optional')->default(false)->after('curriculum_code');
            $table->boolean('is_active')->default(true)->after('is_optional')->index();
            $table->unsignedSmallInteger('display_order')->default(0)->after('is_active');
            $table->string('choice_group', 80)->nullable()->after('display_order');
            $table->index(['course_id', 'school_level_id', 'school_stream_id'], 'subject_level_stream_idx');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::disableForeignKeyConstraints();
            Schema::create('course_school_level_v1', function (Blueprint $table) {
                $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->foreignId('school_level_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['course_id', 'school_level_id']);
            });
            DB::table('course_school_level')->whereNull('school_stream_id')->orderBy('id')->each(fn ($assignment) => DB::table('course_school_level_v1')->insertOrIgnore([
                'tenant_id' => $assignment->tenant_id, 'course_id' => $assignment->course_id,
                'school_level_id' => $assignment->school_level_id, 'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ]));
            Schema::drop('course_school_level');
            Schema::rename('course_school_level_v1', 'course_school_level');
            Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('title_ar'));
            Schema::dropIfExists('school_streams');
            Schema::enableForeignKeyConstraints();

            return;
        }

        Schema::table('course_school_level', function (Blueprint $table) {
            $table->dropIndex('subject_level_stream_idx');
            $table->dropConstrainedForeignId('school_stream_id');
            $table->dropColumn(['id', 'curriculum_code', 'is_optional', 'is_active', 'display_order', 'choice_group']);
            $table->primary(['course_id', 'school_level_id']);
        });
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('title_ar'));
        Schema::dropIfExists('school_streams');
    }
};
