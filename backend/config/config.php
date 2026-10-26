<?php

declare(strict_types=1);

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) ($_ENV['ALLOWED_ORIGINS'] ?? ''))
)));

return [
    'env'   => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),

    'db' => [
        'host'            => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port'            => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name'            => $_ENV['DB_NAME'] ?? 'inventario_sd',
        'user'            => $_ENV['DB_USER'] ?? '',
        'pass'            => $_ENV['DB_PASS'] ?? '',
        'connect_timeout' => 5,
    ],

    'allowed_origins' => $origins,

    'session' => [
        'name'             => 'INVSD_SESSID',
        'idle_timeout'     => (int) ($_ENV['SESSION_IDLE_TIMEOUT'] ?? 1800),
        'absolute_timeout' => (int) ($_ENV['SESSION_ABSOLUTE_TIMEOUT'] ?? 28800),
        'secure'           => strtolower((string) ($_ENV['SESSION_COOKIE_SECURE'] ?? 'auto')), // true | false | auto
        'samesite'         => 'Lax',
    ],

    'auth' => [
        'max_attempts' => 5,
        'lock_minutes' => 15,
    ],
];