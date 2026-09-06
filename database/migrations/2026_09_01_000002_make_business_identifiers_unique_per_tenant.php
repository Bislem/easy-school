<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $identifiers = [
        'students' => 'email', 'courses' => 'code', 'classrooms' => 'code',
        'school_sites' => 'code', 'badge_templates' => 'slug', 'employee_types' => 'slug',
        'staff' => 'employee_code', 'cars' => 'license_plate',
    ];

    public function up(): void
    {
        foreach ($this->identifiers as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->dropUnique([$column]);
                $table->unique(['tenant_id', $column]);
            });
        }
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['social_security_number']);
            $table->unique(['tenant_id', 'social_security_number']);
        });
    }

    public function down(): void
    {
        foreach ($this->identifiers as $tableName => $column) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->dropUnique(['tenant_id', $column]);
                $table->unique($column);
            });
        }
        Schema::table('staff', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'social_security_number']);
            $table->unique('social_security_number');
        });
    }
};
