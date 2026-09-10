<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class RbacAudit
{
    public function record(string $event, Model $related, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'user_id' => auth()->id(), 'event' => $event,
            'related_type' => $related->getMorphClass(), 'related_id' => $related->getKey(),
            'description' => $event, 'old_values' => $oldValues, 'new_values' => $newValues,
            'ip_address' => request()?->ip(), 'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'occurred_at' => now(),
        ]);
    }
}
