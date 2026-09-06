<?php

namespace App\Http\Resources\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'starts_at' => $this->starts_at?->toIso8601String(), 'ends_at' => $this->ends_at?->toIso8601String(), 'status' => $this->status, 'course' => $this->group?->plan?->course?->only(['id', 'title', 'code']), 'group' => $this->group?->only(['id', 'name']), 'room' => $this->classroom ? ['id' => $this->classroom->id, 'name' => $this->classroom->name, 'site' => $this->classroom->site?->only(['id', 'name'])] : null, 'teacher' => $this->teacher?->only(['id', 'name'])];
    }
}
