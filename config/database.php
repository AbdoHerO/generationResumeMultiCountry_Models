<?php

declare(strict_types=1);

// Variable names match the ones CloudOps (and Laravel) write into .env.
return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', '3306'),
    'socket' => env('DB_SOCKET', ''),
    'database' => env('DB_DATABASE', 'cvbuilder'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
];
