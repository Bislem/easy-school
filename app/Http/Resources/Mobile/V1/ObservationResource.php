<?php

namespace App\Http\Resources\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = fn ($user) => $user ? ['id' => $user->id, 'name' => $user->name, 'role' => $user->role?->value ?? $user->role] : null;
        return ['id' => $this->id, 'message' => $this->message, 'author' => $author($this->author), 'created_at' => $this->created_at?->toIso8601String(), 'replies' => $this->whenLoaded('replies', fn () => $this->replies->map(fn ($reply) => ['id' => $reply->id, 'message' => $reply->message, 'author' => $author($reply->author), 'created_at' => $reply->created_at?->toIso8601String()]))];
    }
}
