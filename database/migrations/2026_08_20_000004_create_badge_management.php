<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('badge_templates',function(Blueprint $table){$table->id();$table->string('name');$table->string('slug')->unique();$table->string('primary_color')->default('#f97316');$table->string('secondary_color')->default('#111827');$table->string('text_color')->default('#ffffff');$table->boolean('show_address')->default(true);$table->boolean('show_contact')->default(true);$table->boolean('show_barcode')->default(false);$table->boolean('is_default')->default(false)->index();$table->json('settings')->nullable();$table->timestamps();});
        Schema::create('badges',function(Blueprint $table){$table->id();$table->morphs('badgeable');$table->foreignId('badge_template_id')->nullable()->constrained()->nullOnDelete();$table->foreignId('replaces_badge_id')->nullable()->constrained('badges')->restrictOnDelete();$table->string('card_number')->unique();$table->string('verification_token',64)->unique();$table->string('barcode_value')->nullable()->unique();$table->date('issue_date')->index();$table->date('expiration_date')->nullable()->index();$table->string('status')->default('active')->index();$table->string('first_name');$table->string('last_name');$table->string('person_type')->index();$table->string('role_label');$table->string('formation_label')->nullable();$table->string('group_label')->nullable();$table->text('photo_url_snapshot')->nullable();$table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();$table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('status_changed_at')->nullable();$table->text('status_reason')->nullable();$table->json('metadata')->nullable();$table->timestamps();$table->index(['badgeable_type','badgeable_id','status']);});
        $now=now();DB::table('badge_templates')->insert(['name'=>'Carte Easy School','slug'=>'easy-school-default','primary_color'=>'#f97316','secondary_color'=>'#111827','text_color'=>'#ffffff','show_address'=>true,'show_contact'=>true,'show_barcode'=>false,'is_default'=>true,'created_at'=>$now,'updated_at'=>$now]);
    }
    public function down(): void { Schema::dropIfExists('badges');Schema::dropIfExists('badge_templates'); }
};
