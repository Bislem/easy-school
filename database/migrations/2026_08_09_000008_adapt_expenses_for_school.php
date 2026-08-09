<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expenses', 'payment_method')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('payment_method')->default('cash')->after('vendor');
            });
        }

        DB::table('expenses')->whereIn('type', ['agency', 'maintenance'])->update([
            'type' => 'school',
            'car_id' => null,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('expenses', 'payment_method')) {
            Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn('payment_method'));
        }
    }
};
