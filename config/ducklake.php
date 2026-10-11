<?php

return [
    'cloud' => (bool) env('LARAVEL_CLOUD', false),
    'binary' => base_path('bin/duckdb'),
    'extension_directory' => base_path('var/duckdb-extensions'),
    'data_prefix' => 'ducklake/logs/',
    'mcp_token' => null,
    'flush_batch_limit' => 20,
];
