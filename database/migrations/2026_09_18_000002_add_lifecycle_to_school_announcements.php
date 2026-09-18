<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('school_announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('school_announcements', 'status')) $table->string('status', 20)->default('published')->index()->after('target');
            if (! Schema::hasColumn('school_announcements', 'notified_at')) $table->timestamp('notified_at')->nullable()->after('published_at');
            $table->timestamp('published_at')->nullable()->change();
        });
        DB::table('school_announcements')->whereNull('notified_at')->whereNotNull('published_at')->update(['notified_at' => DB::raw('published_at')]);
    }

    public function down(): void
    {
        Schema::table('school_announcements', function (Blueprint $table) {
            if (Schema::hasColumn('school_announcements', 'notified_at')) $table->dropColumn('notified_at');
            if (Schema::hasColumn('school_announcements', 'status')) $table->dropColumn('status');
        });
    }
};
