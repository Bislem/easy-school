<?php

namespace App\Support;

final class TimetableConflictResult
{
    public function __construct(public array $conflicts = [], public array $warnings = []) {}
    public function blocked(): bool { return $this->conflicts !== []; }
    public function toArray(): array { return ['available' => ! $this->blocked(), 'conflicts' => $this->conflicts, 'warnings' => $this->warnings]; }
}
