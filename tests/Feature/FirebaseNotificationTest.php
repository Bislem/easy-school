<?php

use App\Models\FcmToken;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('exposes only public Firebase browser configuration', function () {
    config([
        'firebase.api_key' => 'public-api-key',
        'firebase.client_email' => 'secret@example.test',
        'firebase.private_key' => 'private-key',
    ]);

    $this->getJson(route('firebase.configuration'))
        ->assertOk()
        ->assertJsonPath('apiKey', 'public-api-key')
        ->assertJsonMissingPath('clientEmail')
        ->assertJsonMissingPath('privateKey');

    $this->get(route('firebase.service-worker'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
        ->assertSee('firebase.initializeApp', false)
        ->assertDontSee('private-key', false);
});

it('stores multiple FCM browser tokens for an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('fcm.tokens.store'), ['token' => 'browser-one'])->assertOk();
    $this->actingAs($user)->postJson(route('fcm.tokens.store'), ['token' => 'browser-two'])->assertOk();

    expect($user->fcmTokens()->count())->toBe(2);
});

it('removes an invalid FCM token reported by HTTP v1', function () {
    config([
        'firebase.project_id' => 'school-project',
        'firebase.client_email' => 'firebase@example.test',
        'firebase.private_key' => 'unused-because-token-is-cached',
    ]);
    Cache::put('firebase.messaging.access_token', 'test-access-token', 60);
    Http::fake([
        'fcm.googleapis.com/*' => Http::response([
            'error' => ['status' => 'UNREGISTERED'],
        ], 404),
    ]);

    $registration = FcmToken::create([
        'user_id' => User::factory()->create()->id,
        'token' => 'expired-token',
        'token_hash' => hash('sha256', 'expired-token'),
    ]);

    expect(app(FirebaseNotificationService::class)->sendToToken($registration, 'Test', 'Message'))->toBeFalse()
        ->and(FcmToken::find($registration->id))->toBeNull();
});
