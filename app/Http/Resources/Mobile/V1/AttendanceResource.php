<?php

namespace App\Http\Resources\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'status' => $this->status, 'is_justified' => $this->is_justified, 'justification' => $this->justification, 'arrival_time' => $this->arrival_time, 'departure_time' => $this->departure_time, 'recorded_at' => $this->recorded_at?->toIso8601String(), 'session' => new SessionResource($this->whenLoaded('session'))];
    }
}
