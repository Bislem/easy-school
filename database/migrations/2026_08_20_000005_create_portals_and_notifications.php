<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('parents',function(Blueprint $t){$t->id();$t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();$t->string('first_name');$t->string('last_name');$t->string('phone')->nullable();$t->string('relationship')->nullable();$t->timestamps();});
  Schema::create('parent_student',function(Blueprint $t){$t->foreignId('parent_id')->constrained()->cascadeOnDelete();$t->foreignId('student_id')->constrained()->cascadeOnDelete();$t->boolean('is_primary')->default(false);$t->timestamps();$t->primary(['parent_id','student_id']);});
  Schema::create('session_attendances',function(Blueprint $t){$t->id();$t->foreignId('training_session_id')->constrained()->cascadeOnDelete();$t->foreignId('student_id')->constrained()->cascadeOnDelete();$t->foreignId('course_enrollment_id')->nullable()->constrained()->nullOnDelete();$t->string('status')->index();$t->timestamp('recorded_at');$t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();$t->text('notes')->nullable();$t->timestamps();$t->unique(['training_session_id','student_id']);});
  Schema::create('portal_notifications',function(Blueprint $t){$t->id();$t->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();$t->string('type')->index();$t->string('title');$t->text('message');$t->nullableMorphs('related');$t->json('data')->nullable();$t->json('channels');$t->json('delivery_state')->nullable();$t->timestamp('read_at')->nullable()->index();$t->timestamp('occurred_at')->index();$t->timestamps();$t->index(['recipient_id','read_at','occurred_at']);});
 }
 public function down(): void {Schema::dropIfExists('portal_notifications');Schema::dropIfExists('session_attendances');Schema::dropIfExists('parent_student');Schema::dropIfExists('parents');}
};
