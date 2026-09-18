<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('report_card_subjects', function (Blueprint $t) { $t->boolean('include_in_general_average')->default(true)->after('coefficient'); $t->boolean('is_informational')->default(false)->after('include_in_general_average'); $t->boolean('is_exempted')->default(false)->after('is_informational'); }); }
 public function down(): void { Schema::table('report_card_subjects', fn(Blueprint $t) => $t->dropColumn(['include_in_general_average','is_informational','is_exempted'])); }
};
