<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('school_announcements', fn (Blueprint $table) => $table->timestamp('published_at')->nullable()->change());
    }

    public function down(): void
    {
        // Draft announcements intentionally have no publication timestamp.
    }
};
