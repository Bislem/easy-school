<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_exceptions', function (Blueprint $table) {
            $table->timestamp('parent_justification_submitted_at')->nullable()->after('justified_at');
            $table->foreignId('parent_justification_submitted_by')->nullable()->after('parent_justification_submitted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_exceptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_justification_submitted_by');
            $table->dropColumn('parent_justification_submitted_at');
        });
    }
};
