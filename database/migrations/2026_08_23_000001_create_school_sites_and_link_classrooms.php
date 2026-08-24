<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('wilaya', 100);
            $table->string('commune', 100)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $siteId = DB::table('school_sites')->insertGetId([
            'name' => 'Site principal', 'code' => 'PRINCIPAL', 'wilaya' => 'Non renseignée',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('school_site_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });
        DB::table('classrooms')->update(['school_site_id' => $siteId]);
        Schema::table('classrooms', function (Blueprint $table) {
            $table->unsignedBigInteger('school_site_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', fn (Blueprint $table) => $table->dropConstrainedForeignId('school_site_id'));
        Schema::dropIfExists('school_sites');
    }
};
