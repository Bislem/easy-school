<?php

namespace App\Events;

use App\Models\AttendanceException;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentAbsent
{
    use Dispatchable, SerializesModels;

    public function __construct(public AttendanceException $exception) {}
}
