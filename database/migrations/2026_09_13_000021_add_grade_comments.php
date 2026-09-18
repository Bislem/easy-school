<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;return new class extends Migration{public function up():void{Schema::table('grades',fn(Blueprint $t)=>$t->text('comment')->nullable());}public function down():void{Schema::table('grades',fn(Blueprint $t)=>$t->dropColumn('comment'));}};
