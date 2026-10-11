<?php

return [
    'cloud' => (bool) env('LARAVEL_CLOUD', false),
    'binary' => base_path('bin/duckdb'),
    'extension_directory' => base_path('var/duckdb-extensions'),
    'data_prefix' => 'ducklake/logs/',
    'threads' => (int) env('DUCKDB_THREADS', 1),
    'memory_limit' => env('DUCKDB_MEMORY_LIMIT', '384MB'),
    'mcp_token' => null,
    'flush_batch_limit' => 20,
];
