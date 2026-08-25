<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('staff', fn (Blueprint $table) => $table->boolean('can_view_student_folders')->default(true)); }
    public function down(): void { Schema::table('staff', fn (Blueprint $table) => $table->dropColumn('can_view_student_folders')); }
};
