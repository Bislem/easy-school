<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('category')->index();
            $table->string('calculation_type')->index();
            $table->decimal('default_amount', 14, 2)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('salary_configuration_salary_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('salary_configuration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_item_id')->constrained()->restrictOnDelete();
            $table->decimal('amount_override', 14, 2)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['salary_configuration_id', 'salary_item_id'], 'salary_config_item_unique');
        });

        Schema::create('staff_salary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('salary_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_configuration_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_override', 14, 2)->nullable();
            $table->string('source')->default('DIRECT');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['staff_id', 'salary_item_id'], 'staff_salary_item_unique');
        });

        Schema::create('salary_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index()->constrained('tenants')->restrictOnDelete();
            $table->foreignId('salary_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->string('item_code')->nullable();
            $table->string('category')->index();
            $table->string('calculation_type');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('rate', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('source')->index();
            $table->unsignedInteger('display_order')->default(0);
            $table->json('snapshot_data')->nullable();
            $table->timestamps();
        });

        $tenantIds = DB::table('salary_configurations')->select('tenant_id')->distinct()->pluck('tenant_id');
        foreach ($tenantIds as $tenantId) {
            foreach (['monthly' => ['Salaire de base', 'BASE_SALARY', 'FIXED_MONTHLY'], 'hourly' => ["Heure d'enseignement", 'BASE_SALARY', 'HOURLY'], 'daily' => ['Journée travaillée', 'BASE_SALARY', 'DAILY'], 'per_session' => ["Séance d'enseignement", 'BASE_SALARY', 'PER_SESSION'], 'custom' => ['Salaire personnalisé', 'BASE_SALARY', 'FIXED_MONTHLY']] as $legacyType => [$name, $category, $calculationType]) {
                $configs = DB::table('salary_configurations')->where('tenant_id', $tenantId)->where('salary_type', $legacyType)->orderBy('id')->get();
                if ($configs->isEmpty()) continue;
                $itemId = DB::table('salary_items')->insertGetId([
                    'tenant_id' => $tenantId, 'name' => $name, 'code' => 'LEGACY-'.strtoupper($legacyType),
                    'category' => $category, 'calculation_type' => $calculationType,
                    'default_amount' => $configs->first()->base_rate, 'active' => true,
                    'description' => 'Rubrique créée automatiquement depuis les configurations historiques.',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($configs as $config) {
                    DB::table('salary_configuration_salary_item')->insert([
                        'tenant_id' => $tenantId, 'salary_configuration_id' => $config->id, 'salary_item_id' => $itemId,
                        'amount_override' => $config->base_rate, 'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // Preserve useful employee assignments inferred from their latest generated configuration.
        $assignments = DB::table('salary_statements as s')
            ->join('salary_configuration_salary_item as csi', 'csi.salary_configuration_id', '=', 's.salary_configuration_id')
            ->select('s.tenant_id', 's.staff_id', 's.salary_configuration_id', 'csi.salary_item_id', 'csi.amount_override', 'csi.display_order')
            ->whereNotNull('s.salary_configuration_id')->orderByDesc('s.period_end')->orderByDesc('s.id')->get();
        foreach ($assignments->unique(fn ($row) => $row->staff_id.'-'.$row->salary_item_id) as $row) {
            DB::table('staff_salary_items')->insert([
                'tenant_id' => $row->tenant_id, 'staff_id' => $row->staff_id, 'salary_item_id' => $row->salary_item_id,
                'salary_configuration_id' => $row->salary_configuration_id, 'amount_override' => $row->amount_override,
                'source' => 'CONFIG', 'display_order' => $row->display_order, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Snapshot legacy statements so old bulletins remain printable with salary lines.
        DB::table('salary_statements')->orderBy('id')->each(function ($statement) {
            $type = $statement->salary_type === 'hourly' ? 'HOURLY' : 'FIXED_MONTHLY';
            DB::table('salary_statement_lines')->insert([
                'tenant_id' => $statement->tenant_id, 'salary_statement_id' => $statement->id,
                'item_name' => $type === 'HOURLY' ? "Heure d'enseignement" : 'Salaire de base',
                'category' => 'BASE_SALARY', 'calculation_type' => $type, 'quantity' => $statement->units,
                'rate' => $statement->base_rate, 'amount' => $statement->gross_salary, 'source' => 'MANUAL',
                'display_order' => 0, 'snapshot_data' => json_encode(['legacy' => true]), 'created_at' => $statement->created_at, 'updated_at' => $statement->updated_at,
            ]);
            $adjustments = DB::table('salary_adjustments')->where('salary_statement_id', $statement->id)->orderBy('id')->get();
            foreach ($adjustments as $index => $adjustment) {
                $deduction = in_array($adjustment->type, ['deduction', 'advance'], true);
                DB::table('salary_statement_lines')->insert([
                    'tenant_id' => $statement->tenant_id, 'salary_statement_id' => $statement->id,
                    'item_name' => $adjustment->label, 'category' => $deduction ? 'DEDUCTION' : 'PRIME',
                    'calculation_type' => 'FIXED_MONTHLY', 'quantity' => 1, 'rate' => $adjustment->amount,
                    'amount' => $deduction ? -$adjustment->amount : $adjustment->amount, 'source' => 'MANUAL',
                    'display_order' => $index + 100, 'snapshot_data' => json_encode(['legacy_adjustment_type' => $adjustment->type]),
                    'created_at' => $adjustment->created_at, 'updated_at' => $adjustment->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_statement_lines');
        Schema::dropIfExists('staff_salary_items');
        Schema::dropIfExists('salary_configuration_salary_item');
        Schema::dropIfExists('salary_items');
    }
};
