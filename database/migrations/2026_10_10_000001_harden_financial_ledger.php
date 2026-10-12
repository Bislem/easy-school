<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropUnique(['reference']);
            $table->unique(['tenant_id', 'reference'], 'financial_transactions_tenant_reference_unique');
            // A transaction can be cancelled at most once.
            $table->unique('reverses_transaction_id', 'financial_transactions_reverses_unique');
        });
        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->string('closed_reason')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->dropColumn(['closed_at', 'closed_reason']);
        });
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropUnique('financial_transactions_reverses_unique');
            $table->dropUnique('financial_transactions_tenant_reference_unique');
            $table->unique('reference');
        });
    }
};
