<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('company_settings', 'client_login_disabled')
            && ! Schema::hasColumn('company_settings', 'teacher_login_disabled')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->renameColumn('client_login_disabled', 'teacher_login_disabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_settings', 'teacher_login_disabled')
            && ! Schema::hasColumn('company_settings', 'client_login_disabled')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->renameColumn('teacher_login_disabled', 'client_login_disabled');
            });
        }
    }
};
