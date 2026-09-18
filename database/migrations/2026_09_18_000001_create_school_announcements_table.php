<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('school_announcements', function (Blueprint $table) { $table->id(); $table->foreignId('tenant_id')->constrained()->cascadeOnDelete(); $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->string('title'); $table->text('message'); $table->string('delivery',20); $table->string('target',20); $table->string('status',20)->default('draft')->index(); $table->json('cycle_ids')->nullable(); $table->string('poster_path')->nullable(); $table->unsignedInteger('recipient_count')->default(0); $table->timestamp('published_at')->nullable(); $table->timestamp('notified_at')->nullable(); $table->timestamps(); $table->index(['tenant_id','published_at']); }); }
 public function down(): void { Schema::dropIfExists('school_announcements'); }
};
