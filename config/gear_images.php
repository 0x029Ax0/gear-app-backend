<?php

return [
    'disk' => env('GEAR_IMAGES_DISK', 'public'),
    'max_bytes' => 5 * 1024 * 1024,
    'max_dimension' => 4096,
    'mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
];
