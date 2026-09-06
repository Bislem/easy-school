<?php

use App\Enums\UserRole;
use App\Models\ContactRequest;
use App\Models\User;

test('the public website stores a validated contact request', function () {
    $this->post(route('contact.store'), [
        'name' => 'Nadia Amari', 'email' => 'nadia@example.test', 'phone' => '0550000011',
        'organization' => 'École Atlas', 'subject' => 'pricing',
        'message' => 'Nous souhaitons recevoir une proposition pour nos deux campus.',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $this->assertDatabaseHas('contact_requests', ['email' => 'nadia@example.test', 'organization' => 'École Atlas', 'status' => 'new']);
});

test('the contact form rejects an invalid subject and short message', function () {
    $this->post(route('contact.store'), ['name' => 'Test', 'email' => 'test@example.test', 'subject' => 'spam', 'message' => 'short'])
        ->assertSessionHasErrors(['subject', 'message']);
    expect(ContactRequest::count())->toBe(0);
});

test('a super administrator can triage and resolve contact requests', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'tenant_id' => null, 'is_active' => true, 'can_login' => true]);
    $contact = ContactRequest::create(['name' => 'Karim', 'email' => 'karim@example.test', 'subject' => 'information', 'message' => 'Je souhaite en savoir plus sur la plateforme.', 'status' => 'new']);

    $this->actingAs($superAdmin, 'super_admin')->patch(route('super-admin.contact-requests.update', $contact), [
        'status' => 'resolved', 'internal_note' => 'Présentation effectuée par téléphone.',
    ])->assertSessionHasNoErrors();

    $contact->refresh();
    expect($contact->status)->toBe('resolved')->and($contact->read_at)->not->toBeNull()->and($contact->resolved_at)->not->toBeNull()->and($contact->handled_by)->toBe($superAdmin->id);
});
