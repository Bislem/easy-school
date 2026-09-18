<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('report_card_subjects', 'tenant_id')) {
            Schema::table('report_card_subjects', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->restrictOnDelete();
            });
        }

        DB::table('report_card_subjects')->whereNull('tenant_id')->update([
            'tenant_id' => DB::raw('(select report_cards.tenant_id from report_cards where report_cards.id = report_card_subjects.report_card_id)'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('report_card_subjects', 'tenant_id')) {
            Schema::table('report_card_subjects', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
        }
    }
};
