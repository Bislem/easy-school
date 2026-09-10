<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('private_school_inscriptions', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->change();
            $table->foreignId('parent_id')->nullable()->change();
            $table->json('applicant_data')->nullable()->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('private_school_inscriptions', function (Blueprint $table) {
            $table->dropColumn('applicant_data');
            $table->foreignId('student_id')->nullable(false)->change();
            $table->foreignId('parent_id')->nullable(false)->change();
        });
    }
};
