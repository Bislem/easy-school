<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_year_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 30)->default('closure');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('applies_to', 20)->default('both');
            $table->boolean('is_paid_for_teachers')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['academic_year_id', 'starts_on', 'ends_on'], 'academic_year_event_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_year_calendar_events');
    }
};
