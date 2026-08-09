<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('tax_enabled')->default(false);
            $table->decimal('tax_rate', 5, 2)->default(7);
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', fn (Blueprint $table) => $table->dropColumn(['tax_enabled', 'tax_rate']));
    }
};
