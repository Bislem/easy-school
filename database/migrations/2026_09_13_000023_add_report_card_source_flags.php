<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{public function up():void{Schema::table('report_cards',fn(Blueprint $t)=>$t->boolean('source_data_changed')->default(false)->after('status'));}public function down():void{Schema::table('report_cards',fn(Blueprint $t)=>$t->dropColumn('source_data_changed'));}};
