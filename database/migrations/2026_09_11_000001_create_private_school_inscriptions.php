<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_school_inscription_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('deadline')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->uuid('public_token')->unique();
            $table->timestamps();
            $table->index(['tenant_id', 'academic_year_id']);
        });

        Schema::create('private_school_campaign_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('private_school_inscription_campaigns')->cascadeOnDelete();
            $table->foreignId('school_level_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('max_places')->nullable();
            $table->boolean('is_open')->default(true);
            $table->timestamps();
            $table->unique(['campaign_id', 'school_level_id'], 'private_school_campaign_level_unique');
        });

        Schema::create('private_school_inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('private_school_inscription_campaigns')->restrictOnDelete();
            $table->foreignId('campaign_level_id')->constrained('private_school_campaign_levels')->restrictOnDelete();
            $table->foreignId('school_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'campaign_id', 'school_level_id'], 'private_school_inscriptions_unique_request');
            $table->index(['academic_year_id', 'school_level_id', 'status'], 'private_school_inscriptions_wizard_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_school_inscriptions');
        Schema::dropIfExists('private_school_campaign_levels');
        Schema::dropIfExists('private_school_inscription_campaigns');
    }
};
