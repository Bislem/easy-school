<?php
use App\Enums\UserRole;
use App\Models\PortalNotification;
use App\Models\SchoolParent;
use App\Models\Student;
use App\Models\StudentObservation;
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

test('an administrator can open an observation for any student and notify the parent', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true, 'can_login' => true]);
    $parentUser = User::factory()->create(['role' => UserRole::PARENT, 'is_active' => true, 'can_login' => true]);
    $parent = SchoolParent::create(['user_id' => $parentUser->id, 'first_name' => 'Nadia', 'last_name' => 'Kaci']);
    $student = Student::create(['first_name' => 'Lina', 'last_name' => 'Kaci', 'phone' => '1', 'is_active' => true]);
    $parent->students()->attach($student);

    $this->actingAs($admin)->post(route('portal.students.observations.store', $student), [
        'message' => 'Merci de prendre contact avec l’administration.',
    ])->assertRedirect();

    expect(StudentObservation::where('student_id', $student->id)->where('author_id', $admin->id)->exists())->toBeTrue()
        ->and(PortalNotification::where('recipient_id', $parentUser->id)->where('type', 'observation.admin_added')->exists())->toBeTrue();
});
