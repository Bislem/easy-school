<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payroll_regulations', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->restrictOnDelete();
            $table->string('country', 2)->default('DZ')->index(); $table->string('name'); $table->string('version');
            $table->date('valid_from')->index(); $table->date('valid_until')->nullable()->index(); $table->boolean('active')->default(true)->index();
            $table->json('rules'); $table->json('source_references')->nullable(); $table->timestamps();
            $table->unique(['tenant_id', 'country', 'version']);
        });
        Schema::table('salary_items', function (Blueprint $table) {
            $table->boolean('subject_to_cnas')->nullable()->after('calculation_type');
            $table->boolean('subject_to_irg')->nullable()->after('subject_to_cnas');
            $table->string('salary_item_nature')->default('OTHER_EARNING')->after('subject_to_irg')->index();
            $table->string('irg_treatment')->default('MONTHLY')->after('salary_item_nature');
        });
        DB::table('salary_items')->where('category','BASE_SALARY')->update(['salary_item_nature'=>'BASE_SALARY','subject_to_cnas'=>true,'subject_to_irg'=>true]);
        DB::table('salary_items')->where('category','PRIME')->update(['salary_item_nature'=>'PRIME']);
        DB::table('salary_items')->where('category','INDEMNITY')->update(['salary_item_nature'=>'INDEMNITY']);
        DB::table('salary_items')->where('category','DEDUCTION')->update(['salary_item_nature'=>'DEDUCTION','subject_to_cnas'=>false,'subject_to_irg'=>false]);
        DB::table('salary_items')->where('calculation_type','HOURLY')->update(['salary_item_nature'=>'HOURLY_WORK','subject_to_cnas'=>true,'subject_to_irg'=>true]);
        Schema::table('staff', fn (Blueprint $table) => $table->string('nin', 30)->nullable()->after('social_security_number')->index());
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('cnas_employer_number', 50)->nullable(); $table->string('nis', 50)->nullable();
            $table->string('legal_activity')->nullable(); $table->string('cnas_portal_url')->nullable(); $table->string('dgi_portal_url')->nullable();
        });
        Schema::table('salary_statements', function (Blueprint $table) {
            $table->foreignId('payroll_regulation_id')->nullable()->after('salary_configuration_id')->constrained()->nullOnDelete();
            $table->decimal('cnas_base', 14, 2)->default(0); $table->decimal('employee_cnas', 14, 2)->default(0);
            $table->decimal('irg_base', 14, 2)->default(0); $table->decimal('irg_amount', 14, 2)->default(0);
            $table->decimal('employer_contributions', 14, 2)->default(0); $table->decimal('total_employer_cost', 14, 2)->default(0);
            $table->json('statutory_calculation')->nullable(); $table->json('legal_warnings')->nullable();
        });
        Schema::create('salary_statutory_lines', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('salary_statement_id')->constrained()->cascadeOnDelete(); $table->string('code')->index(); $table->string('label');
            $table->decimal('base', 14, 2)->default(0); $table->decimal('rate', 8, 4)->nullable(); $table->decimal('amount', 14, 2);
            $table->boolean('affects_net')->default(false); $table->boolean('employer_charge')->default(false); $table->json('details')->nullable(); $table->timestamps();
        });
        Schema::create('payroll_declarations', function (Blueprint $table) {
            $table->id(); $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->restrictOnDelete();
            $table->string('declaration_type')->index(); $table->string('period')->index(); $table->foreignId('payroll_regulation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('employee_count')->default(0); $table->decimal('declared_amount', 16, 2)->default(0); $table->string('status')->default('DRAFT')->index();
            $table->json('validation_result')->nullable(); $table->json('generated_files')->nullable(); $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('declared_at')->nullable(); $table->timestamps();
            $table->unique(['tenant_id', 'declaration_type', 'period']);
        });

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('payroll_regulations')->insert(['tenant_id'=>$tenantId,'country'=>'DZ','name'=>'Régime général algérien','version'=>'DZ-GENERAL-2022','valid_from'=>'2022-01-01','active'=>true,
                'rules'=>json_encode(['cnas'=>['employee_rate'=>0.09,'employer_rate'=>0.25,'social_works_rate'=>0.005],'irg'=>['annual_brackets'=>[['up_to'=>240000,'rate'=>0],['up_to'=>480000,'rate'=>0.23],['up_to'=>960000,'rate'=>0.27],['up_to'=>1920000,'rate'=>0.30],['up_to'=>3840000,'rate'=>0.33],['up_to'=>null,'rate'=>0.35]],'abatement_rate'=>0.40,'abatement_min_monthly'=>1000,'abatement_max_monthly'=>1500,'exemption_monthly'=>30000,'low_income_upper'=>35000,'low_income_multiplier'=>137/51,'low_income_offset'=>27925/8,'occasional_rate'=>0.10]]),
                'source_references'=>json_encode(['cnas'=>'https://cnas.dz/fr/employeur/','irg'=>'https://mfdgi.gov.dz/fr/particuliers/irg-traitements-et-salaires']),'created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('payroll_declarations'); Schema::dropIfExists('salary_statutory_lines');
        Schema::table('salary_statements', function(Blueprint $t){$t->dropConstrainedForeignId('payroll_regulation_id');$t->dropColumn(['cnas_base','employee_cnas','irg_base','irg_amount','employer_contributions','total_employer_cost','statutory_calculation','legal_warnings']);});
        Schema::table('company_settings', fn(Blueprint $t)=>$t->dropColumn(['cnas_employer_number','nis','legal_activity','cnas_portal_url','dgi_portal_url']));
        Schema::table('staff', fn(Blueprint $t)=>$t->dropColumn('nin'));
        Schema::table('salary_items', fn(Blueprint $t)=>$t->dropColumn(['subject_to_cnas','subject_to_irg','salary_item_nature','irg_treatment']));
        Schema::dropIfExists('payroll_regulations');
    }
};
