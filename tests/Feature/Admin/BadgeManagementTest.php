<?php

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Badge;
use App\Models\BadgeTemplate;
use App\Models\Student;
use App\Models\User;

test('a student badge can be issued and reissued without deleting history', function () {
    $admin=User::factory()->create(['role'=>UserRole::ADMIN]);
    $student=Student::create(['first_name'=>'Lina','last_name'=>'Kaci','phone'=>'0550000000','status'=>StudentStatus::ACTIVE,'is_active'=>true]);
    $template=BadgeTemplate::create(['name'=>'Test','slug'=>'test','is_default'=>true]);
    $this->actingAs($admin)->post(route('admin.badges.store'),['person_type'=>'student','person_id'=>$student->id,'badge_template_id'=>$template->id,'issue_date'=>today()->toDateString(),'expiration_date'=>today()->addYear()->toDateString(),'barcode_enabled'=>true])->assertSessionHasNoErrors();
    $old=Badge::firstOrFail();
    expect($old->card_number)->not->toBeEmpty()->and($old->verification_token)->toHaveLength(64);
    $this->actingAs($admin)->post(route('admin.badges.reissue',$old),['issue_date'=>today()->toDateString(),'expiration_date'=>today()->addYear()->toDateString(),'reason'=>'Carte endommagée'])->assertSessionHasNoErrors();
    expect($old->refresh()->status->value)->toBe('replaced')->and(Badge::where('replaces_badge_id',$old->id)->where('status','active')->exists())->toBeTrue()->and(Badge::count())->toBe(2);
});

test('qr payload uses only an opaque verification url', function () {
    $token=hash('sha256','secret');
    $badge=new Badge(['verification_token'=>$token]);
    expect($badge->verification_url)->toContain($token)->not->toContain('first_name');
});
