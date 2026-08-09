<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->decimal('security_deposit', 12, 2)->default(0)->after('price_per_day');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('security_deposit_amount', 12, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn('security_deposit_amount'));
        Schema::table('cars', fn (Blueprint $table) => $table->dropColumn('security_deposit'));
    }
};
