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

it('uses a service account JSON file and derives its project ID', function () {
    $credentialsPath = tempnam(sys_get_temp_dir(), 'firebase-credentials-');
    file_put_contents($credentialsPath, json_encode([
        'type' => 'service_account',
        'project_id' => 'json-school-project',
        'private_key' => 'test-private-key',
        'client_email' => 'firebase@example.test',
    ], JSON_THROW_ON_ERROR));

    try {
        config([
            'firebase.credentials' => $credentialsPath,
            'firebase.project_id' => null,
            'firebase.client_email' => null,
            'firebase.private_key' => null,
        ]);
        Cache::put('firebase.messaging.access_token', 'test-access-token', 60);
        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'messages/test'], 200),
        ]);

        $registration = FcmToken::create([
            'user_id' => User::factory()->create()->id,
            'token' => 'json-browser-token',
            'token_hash' => hash('sha256', 'json-browser-token'),
        ]);

        expect(app(FirebaseNotificationService::class)->configured())->toBeTrue()
            ->and(app(FirebaseNotificationService::class)->sendToToken($registration, 'Test', 'Message'))->toBeTrue();

        Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/json-school-project/messages:send'
            && $request->hasHeader('Authorization', 'Bearer test-access-token'));
    } finally {
        @unlink($credentialsPath);
    }
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

it('removes an unregistered token and continues delivering to the users other browsers', function () {
    config([
        'firebase.project_id' => 'school-project',
        'firebase.client_email' => 'firebase@example.test',
        'firebase.private_key' => 'unused-because-token-is-cached',
    ]);
    Cache::put('firebase.messaging.access_token', 'test-access-token', 60);
    Http::fakeSequence('fcm.googleapis.com/*')
        ->push([
            'error' => [
                'status' => 'NOT_FOUND',
                'message' => 'Device unregistered.',
                'details' => [[
                    '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                    'errorCode' => 'UNREGISTERED',
                ]],
            ],
        ], 404)
        ->push(['name' => 'messages/delivered'], 200);

    $user = User::factory()->create();
    $stale = FcmToken::create([
        'user_id' => $user->id,
        'token' => 'stale-browser-token',
        'token_hash' => hash('sha256', 'stale-browser-token'),
    ]);
    $current = FcmToken::create([
        'user_id' => $user->id,
        'token' => 'current-browser-token',
        'token_hash' => hash('sha256', 'current-browser-token'),
    ]);

    app(FirebaseNotificationService::class)->sendToUser($user, 'Test', 'Message');

    expect(FcmToken::find($stale->id))->toBeNull()
        ->and($current->fresh()->last_used_at)->not->toBeNull();
});
