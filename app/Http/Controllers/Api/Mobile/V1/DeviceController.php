<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse { $data = $request->validate(['token' => ['required', 'string', 'max:4096'], 'platform' => ['required', 'in:ios,android'], 'device_name' => ['nullable', 'string', 'max:100']]); $device = FcmToken::updateOrCreate(['token_hash' => hash('sha256', $data['token'])], ['token' => $data['token'], 'user_id' => $request->user()->id, 'user_agent' => trim($data['platform'].' '.($data['device_name'] ?? '')), 'last_used_at' => now()]); return response()->json(['data' => ['id' => $device->id, 'platform' => $data['platform'], 'registered_at' => $device->created_at?->toIso8601String()]], $device->wasRecentlyCreated ? 201 : 200); }
    public function destroy(Request $request, FcmToken $device): JsonResponse { abort_unless($device->user_id === $request->user()->id, 403); $device->delete(); return response()->json(null, 204); }
}
