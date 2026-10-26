<?php

return [
    'disk' => env('SUPPORTING_DOCUMENTS_DISK', 'local'),
    'max_files' => 6,
    'max_size_kb' => 5120,
    'max_dimension' => 12000,
    'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
];
