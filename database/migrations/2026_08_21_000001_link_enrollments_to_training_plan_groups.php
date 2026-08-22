<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('course_enrollments', 'training_plan_group_id')) {
            Schema::table('course_enrollments', function (Blueprint $table) {
                $table->foreignId('training_plan_group_id')->nullable()->after('enrollment_form_id')
                    ->constrained('training_plan_groups')->nullOnDelete();
            });
        }

        // Preserve the existing public-form/group-number model while giving every
        // matching inscription a stable group relationship.
        DB::table('course_enrollments')
            ->whereNotNull('enrollment_form_id')
            ->whereNotNull('group_number')
            ->orderBy('id')
            ->eachById(function ($enrollment) {
                $groupId = DB::table('training_plan_groups')
                    ->join('training_plans', 'training_plans.id', '=', 'training_plan_groups.training_plan_id')
                    ->where('training_plans.enrollment_form_id', $enrollment->enrollment_form_id)
                    ->where('training_plan_groups.group_number', $enrollment->group_number)
                    ->value('training_plan_groups.id');

                if ($groupId) {
                    DB::table('course_enrollments')->where('id', $enrollment->id)
                        ->update(['training_plan_group_id' => $groupId]);
                }
            });

        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->unsignedBigInteger('enrollment_form_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('training_plan_group_id');
        });
    }
};
