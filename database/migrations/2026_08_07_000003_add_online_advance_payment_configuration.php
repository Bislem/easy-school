<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->decimal('online_advance_percentage', 5, 2)->default(0);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('advance_percentage', 5, 2)->default(0);
            $table->decimal('required_advance_amount', 12, 2)->default(0);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_type')->default('rental')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('payment_type'));
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['advance_percentage', 'required_advance_amount']));
        Schema::table('company_settings', fn (Blueprint $table) => $table->dropColumn('online_advance_percentage'));
    }
};
