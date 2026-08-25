<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseNotificationService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function configured(): bool
    {
        return filled(config('firebase.project_id'))
            && filled(config('firebase.client_email'))
            && filled(config('firebase.private_key'));
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        if (! $this->configured()) {
            return;
        }

        $user->fcmTokens()->get()->each(fn (FcmToken $token) => $this->sendToToken($token, $title, $body, $data));
    }

    public function sendToToken(FcmToken $registration, string $title, string $body, array $data = []): bool
    {
        $payloadData = collect([
            ...$data,
            'title' => $title,
            'body' => $body,
        ])->mapWithKeys(fn ($value, $key) => [(string) $key => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES)])->all();

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(15)
            ->post(sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', config('firebase.project_id')), [
                'message' => [
                    'token' => $registration->token,
                    'data' => $payloadData,
                    'webpush' => [
                        'headers' => ['Urgency' => 'high', 'TTL' => '86400'],
                    ],
                ],
            ]);

        if ($response->successful()) {
            $registration->update(['last_used_at' => now()]);

            return true;
        }

        if ($this->isInvalidTokenResponse($response->status(), $response->json())) {
            $registration->delete();

            return false;
        }

        $response->throw();
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase.messaging.access_token', now()->addMinutes(50), function (): string {
            $privateKey = str_replace('\\n', "\n", (string) config('firebase.private_key'));
            $credentials = new ServiceAccountCredentials(self::SCOPE, [
                'type' => 'service_account',
                'project_id' => config('firebase.project_id'),
                'private_key' => $privateKey,
                'client_email' => config('firebase.client_email'),
                'token_uri' => 'https://oauth2.googleapis.com/token',
            ]);
            $token = $credentials->fetchAuthToken();

            return $token['access_token'] ?? throw new RuntimeException('Firebase OAuth access token could not be created.');
        });
    }

    private function isInvalidTokenResponse(int $status, array $response): bool
    {
        if ($status === 404 && data_get($response, 'error.status') === 'UNREGISTERED') {
            return true;
        }

        if ($status !== 400) {
            return false;
        }

        return collect(data_get($response, 'error.details', []))->contains(
            fn (array $detail) => ($detail['@type'] ?? null) === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
                && in_array($detail['errorCode'] ?? null, ['INVALID_ARGUMENT', 'UNREGISTERED'], true),
        );
    }
}
