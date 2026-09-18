<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_exceptions', function (Blueprint $table) {
            $table->string('parent_justification_attachment_path')->nullable()->after('parent_justification_submitted_by');
            $table->string('parent_justification_attachment_name')->nullable()->after('parent_justification_attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_exceptions', function (Blueprint $table) {
            $table->dropColumn(['parent_justification_attachment_path', 'parent_justification_attachment_name']);
        });
    }
};
