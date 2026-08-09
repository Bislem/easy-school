<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_forms', function (Blueprint $table) {
            $table->unsignedInteger('students_per_group')->nullable()->after('groups_count');
        });

        DB::table('enrollment_forms')->whereNull('classroom_id')->orderBy('id')->each(function ($form) {
            DB::table('enrollment_forms')->where('id', $form->id)->update([
                'students_per_group' => max(1, (int) ceil($form->max_students / max(1, $form->groups_count))),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_forms', fn (Blueprint $table) => $table->dropColumn('students_per_group'));
    }
};
