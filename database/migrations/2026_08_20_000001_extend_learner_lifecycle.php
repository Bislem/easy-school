<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
            $table->string('photo_path')->nullable()->after('last_name');
            $table->string('parent_phone', 50)->nullable()->after('phone');
            $table->date('registration_date')->nullable()->after('birth_date')->index();
            $table->string('school_level')->nullable()->after('registration_date');
            $table->string('status')->default('active')->after('notes')->index();
            $table->string('email')->nullable()->change();
        });
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->string('status')->default('new')->after('student_id')->index();
            $table->string('level')->nullable()->after('birth_date')->index();
            $table->string('parent_phone', 50)->nullable()->after('phone');
            $table->text('notes')->nullable()->after('group_number');
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('registered_at')->nullable()->index();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
        Schema::create('student_histories', function (Blueprint $table) {
            $table->id(); $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index(); $table->string('from_status')->nullable(); $table->string('to_status')->nullable();
            $table->text('description')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('enrollment_histories', function (Blueprint $table) {
            $table->id(); $table->foreignId('course_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->index(); $table->string('from_status')->nullable(); $table->string('to_status')->nullable();
            $table->text('description')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
        });

        DB::table('students')->update(['status' => DB::raw("CASE WHEN is_active = 1 THEN 'active' ELSE 'stopped' END"), 'registration_date' => DB::raw('DATE(created_at)')]);
        DB::table('course_enrollments')->whereNotNull('student_id')->update(['status' => 'registered', 'registered_at' => DB::raw('confirmed_at')]);
        DB::table('course_enrollments')->whereNull('student_id')->whereNotNull('confirmed_at')->update(['status' => 'waiting']);
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_histories'); Schema::dropIfExists('student_histories');
        Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropColumn(['status','level','parent_phone','notes','contacted_at','approved_at','registered_at','rejected_at','cancelled_at']));
        Schema::table('students', function (Blueprint $table) { $table->dropConstrainedForeignId('user_id'); $table->string('email')->nullable(false)->change(); $table->dropColumn(['photo_path','parent_phone','registration_date','school_level','status']); });
    }
};
