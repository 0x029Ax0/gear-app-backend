<?php

return [
    'max_pending_per_user' => (int) env('PRODUCT_IMPORT_MAX_PENDING', 5),
    'rate_limit_per_minute' => (int) env('PRODUCT_IMPORT_RATE_LIMIT', 10),
    'ttl_hours' => (int) env('PRODUCT_IMPORT_TTL_HOURS', 24),
    'fetch_timeout' => (int) env('PRODUCT_IMPORT_FETCH_TIMEOUT', 10),
    'max_bytes' => (int) env('PRODUCT_IMPORT_MAX_BYTES', 2_000_000),
    'max_image_bytes' => (int) env('PRODUCT_IMPORT_MAX_IMAGE_BYTES', 5_000_000),
    'max_redirects' => (int) env('PRODUCT_IMPORT_MAX_REDIRECTS', 3),
    'image_disk' => env('PRODUCT_IMPORT_IMAGE_DISK', 'public'),
];
