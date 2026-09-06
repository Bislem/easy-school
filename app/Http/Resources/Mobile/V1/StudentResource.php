<?php

namespace App\Http\Resources\Mobile\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'first_name' => $this->first_name, 'last_name' => $this->last_name, 'full_name' => $this->full_name, 'photo_url' => $this->photo_url, 'email' => $this->email, 'phone' => $this->phone, 'birth_date' => $this->birth_date?->format('Y-m-d'), 'address' => $this->address, 'school_level' => $this->school_level, 'status' => $this->status?->value ?? $this->status, 'registration_date' => $this->registration_date?->format('Y-m-d')];
    }
}
