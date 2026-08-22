<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_teacher')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('employee_type_id')->constrained()->restrictOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('photo_path')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('hire_date')->nullable()->index();
            $table->string('employment_status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->string('employee_code')->unique();
            $table->string('identification_type')->nullable();
            $table->string('identification_number')->nullable();
            $table->date('identification_expires_at')->nullable();
            $table->text('identification_notes')->nullable();
            $table->timestamps();
            $table->index(['employee_type_id', 'employment_status']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('employee_id')->constrained('staff')->nullOnDelete();
        });

        $now = now();
        $types = [
            ['name' => 'Enseignant', 'slug' => 'teacher', 'is_teacher' => true],
            ['name' => 'Secrétaire', 'slug' => 'secretary'], ['name' => 'Administrateur', 'slug' => 'administrator'],
            ['name' => 'Comptable', 'slug' => 'accountant'], ['name' => 'Réceptionniste', 'slug' => 'receptionist'],
            ['name' => 'Maintenance', 'slug' => 'maintenance'], ['name' => 'Manager', 'slug' => 'manager'],
            ['name' => 'Autre', 'slug' => 'other'],
        ];
        foreach ($types as $order => $type) DB::table('employee_types')->insert($type + ['is_teacher' => false, 'is_active' => true, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now]);

        $typeIds = DB::table('employee_types')->pluck('id', 'slug');
        DB::table('users')->whereIn('role', ['teacher', 'employee'])->orderBy('id')->each(function ($user) use ($typeIds, $now) {
            $parts = preg_split('/\s+/', trim($user->name), 2);
            $slug = $user->role === 'teacher' ? 'teacher' : 'other';
            DB::table('staff')->insert([
                'user_id' => $user->id, 'employee_type_id' => $typeIds[$slug],
                'first_name' => $parts[0] ?: $user->name, 'last_name' => $parts[1] ?? '',
                'phone' => $user->phone, 'email' => $user->email, 'birth_date' => $user->birth_date,
                'employment_status' => $user->is_active ? 'active' : 'inactive',
                'employee_code' => 'EMP-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                'notes' => $user->job_title, 'created_at' => $now, 'updated_at' => $now,
            ]);
        });
        DB::statement('UPDATE expenses SET staff_id = (SELECT id FROM staff WHERE staff.user_id = expenses.employee_id) WHERE employee_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('staff_id'));
        Schema::dropIfExists('staff');
        Schema::dropIfExists('employee_types');
    }
};
