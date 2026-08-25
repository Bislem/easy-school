<?php

namespace App\Services;

use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class NotificationDispatcher
{
    public function __construct(private FirebaseNotificationService $firebase) {}

    public function send(User|int $recipient, string $type, string $title, string $message, ?Model $related = null, array $data = []): PortalNotification
    {
        $id = $recipient instanceof User ? $recipient->id : $recipient;
        $channels = config('school_notifications.default_channels', ['in_app']);
        $notification = PortalNotification::create(['recipient_id' => $id, 'type' => $type, 'title' => $title, 'message' => $message, 'related_type' => $related?->getMorphClass(), 'related_id' => $related?->getKey(), 'data' => $data, 'channels' => $channels, 'delivery_state' => collect($channels)->mapWithKeys(fn ($c) => [$c => $c === 'in_app' ? 'delivered' : 'pending'])->all(), 'occurred_at' => now()]);
        $user = $recipient instanceof User ? $recipient : User::find($id);
        if ($user) {
            rescue(
                fn () => $this->firebase->sendToUser($user, $title, $message, [
                    ...$data,
                    'notification_id' => $notification->id,
                    'type' => $type,
                    'related_id' => $related?->getKey() ?? '',
                    'url' => $data['url'] ?? '/dashboard',
                ]),
                report: true,
            );
        }

        return $notification;
    }
}
