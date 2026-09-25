<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable()->after('organization_type');
        });

        DB::table('tenants')
            ->where('account_type', 'demo')
            ->whereNull('trial_started_at')
            ->update(['trial_started_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('trial_started_at'));
    }
};
