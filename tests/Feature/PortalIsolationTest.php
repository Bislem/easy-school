<?php
use App\Enums\UserRole;
use App\Models\PortalNotification;
use App\Models\SchoolParent;
use App\Models\Student;
use App\Models\User;

test('a parent relationship contains only explicitly linked children', function () {
    $user=User::factory()->create(['role'=>UserRole::PARENT]);$parent=SchoolParent::create(['user_id'=>$user->id,'first_name'=>'Amine','last_name'=>'Kaci']);
    $linked=Student::create(['first_name'=>'Lina','last_name'=>'Kaci','phone'=>'1','is_active'=>true]);$unrelated=Student::create(['first_name'=>'Sara','last_name'=>'Ali','phone'=>'2','is_active'=>true]);$parent->students()->attach($linked);
    expect($parent->students()->whereKey($linked->id)->exists())->toBeTrue()->and($parent->students()->whereKey($unrelated->id)->exists())->toBeFalse();
});

test('users cannot mark another users notification as read', function () {
    $owner=User::factory()->create(['role'=>UserRole::TEACHER]);$other=User::factory()->create(['role'=>UserRole::TEACHER]);$notification=PortalNotification::create(['recipient_id'=>$owner->id,'type'=>'test','title'=>'Test','message'=>'Private','channels'=>['in_app'],'occurred_at'=>now()]);
    $this->actingAs($other)->patch(route('portal.notifications.read',$notification))->assertForbidden();
    expect($notification->refresh()->read_at)->toBeNull();
});
