<?php

declare(strict_types=1);

/*
 * Front controller. Every request that is not a real file in public/ arrives here.
 */

define('BASE_PATH', dirname(__DIR__));

// PHP's built-in server (php -S) serves existing files itself.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require BASE_PATH . '/app/bootstrap.php';

App\Core\App::run();
