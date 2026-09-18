<?php
return ['periods' => [
    'trimester_1' => '1er trimestre', 'trimester_2' => '2e trimestre', 'trimester_3' => '3e trimestre', 'annual' => 'Bulletin annuel / final',
], 'annual_weights' => ['trimester_1' => 1, 'trimester_2' => 1, 'trimester_3' => 1],
// Set this to the existing grade model once the assessment module is enabled.
'grade_model' => App\Models\Grade::class,
'grade_fields' => ['student_id' => 'student_id', 'enrollment_id' => 'student_academic_enrollment_id', 'subject_id' => 'subject_id', 'period' => 'period_key', 'value' => 'value', 'weight' => 'weight', 'status' => 'status'],
];
