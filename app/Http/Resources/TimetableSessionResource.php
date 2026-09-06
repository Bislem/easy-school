<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimetableSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'series_id' => $this->series_id, 'parent_session_id' => $this->parent_session_id,
            'day' => $this->day, 'effective_date' => $this->effective_date?->toDateString(), 'start_time' => $this->start_time, 'end_time' => $this->end_time,
            'recurrence' => $this->recurrence, 'change_type' => $this->change_type, 'status' => $this->status, 'notes' => $this->notes,
            'group' => $this->whenLoaded('group'), 'subject' => $this->whenLoaded('subject'),
            'teacher' => $this->whenLoaded('teacher'), 'room' => $this->whenLoaded('room'),
            'academic_period' => $this->whenLoaded('academicPeriod'),
            'reservation' => $this->whenLoaded('reservation'),
            'warnings' => $this->when(isset($this->warnings), $this->warnings ?? []),
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
