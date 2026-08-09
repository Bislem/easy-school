<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code', 50)->unique();
            $table->string('category')->nullable()->index();
            $table->unsignedInteger('duration_hours')->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->text('prerequisites')->nullable();
            $table->boolean('is_certified')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
