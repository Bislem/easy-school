<?php

namespace App\Http\Controllers;

use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FcmTokenController extends Controller
{
    public function configuration(): JsonResponse
    {
        return response()->json([
            'apiKey' => config('firebase.api_key'),
            'authDomain' => config('firebase.auth_domain'),
            'projectId' => config('firebase.project_id'),
            'messagingSenderId' => config('firebase.messaging_sender_id'),
            'appId' => config('firebase.app_id'),
            'vapidKey' => config('firebase.vapid_key'),
        ]);
    }

    public function serviceWorker(): Response
    {
        $config = json_encode([
            'apiKey' => config('firebase.api_key'),
            'authDomain' => config('firebase.auth_domain'),
            'projectId' => config('firebase.project_id'),
            'messagingSenderId' => config('firebase.messaging_sender_id'),
            'appId' => config('firebase.app_id'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $script = view('firebase-messaging-service-worker', compact('config'))->render();

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);

        FcmToken::updateOrCreate(
            ['token_hash' => hash('sha256', $data['token'])],
            ['token' => $data['token'], 'user_id' => $request->user()->id, 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'last_used_at' => now()],
        );

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:4096']]);
        $request->user()->fcmTokens()->where('token_hash', hash('sha256', $data['token']))->delete();

        return response()->json(['subscribed' => false]);
    }
}
