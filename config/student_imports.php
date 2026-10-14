<?php

return [
    'max_kilobytes' => (int) env('STUDENT_IMPORT_MAX_KB', 10240),
    'max_rows' => (int) env('STUDENT_IMPORT_MAX_ROWS', 5000),
    'max_columns' => 50,
    'chunk_size' => 200,
    'retention_days' => 30,
];
