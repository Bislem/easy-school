<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_configurations', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->foreignId('staff_id')->nullable()->change();
        });

        DB::table('salary_configurations')->orderBy('id')->each(function ($configuration) {
            DB::table('salary_configurations')->where('id', $configuration->id)->update([
                'name' => ucfirst(str_replace('_', ' ', $configuration->salary_type)).' - '.number_format((float) $configuration->base_rate, 2, '.', ''),
                'staff_id' => null,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('salary_configurations')->whereNull('staff_id')->delete();
        Schema::table('salary_configurations', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable(false)->change();
            $table->dropColumn('name');
        });
    }
};
