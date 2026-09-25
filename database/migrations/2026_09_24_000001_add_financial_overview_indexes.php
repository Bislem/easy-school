<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->index(['tenant_id', 'domain', 'status'], 'financial_accounts_tenant_domain_status_idx');
            $table->index(['tenant_id', 'academic_year_id', 'domain'], 'financial_accounts_tenant_year_domain_idx');
            $table->index(['tenant_id', 'student_id', 'domain'], 'financial_accounts_tenant_student_domain_idx');
        });
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->index(['tenant_id', 'transaction_date', 'type'], 'financial_transactions_tenant_date_type_idx');
            $table->index(['tenant_id', 'payment_method', 'transaction_date'], 'financial_transactions_tenant_method_date_idx');
            $table->index(['financial_account_id', 'transaction_date'], 'financial_transactions_account_date_idx');
        });
        Schema::table('financial_installments', function (Blueprint $table): void {
            $table->index(['tenant_id', 'status', 'due_date'], 'financial_installments_tenant_status_due_idx');
            $table->index(['financial_account_id', 'status', 'due_date'], 'financial_installments_account_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table): void {
            $table->dropIndex('financial_accounts_tenant_domain_status_idx');
            $table->dropIndex('financial_accounts_tenant_year_domain_idx');
            $table->dropIndex('financial_accounts_tenant_student_domain_idx');
        });
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropIndex('financial_transactions_tenant_date_type_idx');
            $table->dropIndex('financial_transactions_tenant_method_date_idx');
            $table->dropIndex('financial_transactions_account_date_idx');
        });
        Schema::table('financial_installments', function (Blueprint $table): void {
            $table->dropIndex('financial_installments_tenant_status_due_idx');
            $table->dropIndex('financial_installments_account_status_due_idx');
        });
    }
};
