<?php

return [
    'max_kilobytes' => (int) env('STUDENT_IMPORT_MAX_KB', 10240),
    'max_rows' => (int) env('STUDENT_IMPORT_MAX_ROWS', 20000),
    'max_columns' => 50,
    'chunk_size' => 200,
    // Rows read from the spreadsheet per pass: each pass scans the whole file again.
    'read_chunk_size' => 2000,
    // Above this number of rows, analysis and confirmation run in the queue (the page follows the progress).
    'queue_threshold' => (int) env('STUDENT_IMPORT_QUEUE_THRESHOLD', 1000),
    // Longest analysis or confirmation in the queue, in seconds (keep below queue.connections.*.retry_after).
    'job_timeout' => 600,
    // Queue of the import jobs: its own worker, so that OTP emails (default queue) never wait behind an import.
    'queue' => env('STUDENT_IMPORT_QUEUE', 'imports'),
    // Cache store of the progress bar: outside the database (the work runs in one transaction).
    // With several web servers, use a shared store (redis).
    'progress_store' => env('STUDENT_IMPORT_PROGRESS_STORE', 'file'),
    'retention_days' => 30,
];
