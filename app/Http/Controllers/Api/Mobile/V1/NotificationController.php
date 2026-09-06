<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\V1\NotificationResource;
use App\Models\PortalNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request) { return NotificationResource::collection($request->user()->portalNotifications()->paginate(25)); }
    public function read(Request $request, PortalNotification $notification): JsonResponse { abort_unless($notification->recipient_id === $request->user()->id, 403); $notification->update(['read_at' => $notification->read_at ?? now()]); return response()->json(['data' => new NotificationResource($notification)]); }
    public function readAll(Request $request): JsonResponse { $request->user()->portalNotifications()->whereNull('read_at')->update(['read_at' => now()]); return response()->json(['message' => 'Toutes les notifications ont été lues.']); }
    public function announcements(Request $request) { return NotificationResource::collection($request->user()->portalNotifications()->where(fn ($q) => $q->where('type', 'like', '%announcement%')->orWhere('type', 'like', '%event%'))->paginate(20)); }
}
