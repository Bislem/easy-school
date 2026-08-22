<?php

return [
    'warning_threshold' => (float) env('ATTENDANCE_WARNING_THRESHOLD', 75),
    'consecutive_absence_warning' => (int) env('ATTENDANCE_CONSECUTIVE_WARNING', 2),
];
