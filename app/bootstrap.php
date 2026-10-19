<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

$autoload = BASE_PATH . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit("Dependencies are missing. Run: composer install\n");
}
require $autoload;
require __DIR__ . '/helpers.php';

// .env next to the code; or, in a release layout (<app>/releases/<id>, with <app>/shared/.env as
// CloudOps, Deployer and Capistrano use), the shared one when the release has none.
$envFile = BASE_PATH . '/.env';
if (!is_file($envFile) && basename(dirname(BASE_PATH)) === 'releases') {
    $envFile = dirname(BASE_PATH, 2) . '/shared/.env';
}
App\Core\Env::load($envFile);
unset($envFile);

date_default_timezone_set((string) config('app.timezone', 'UTC'));
mb_internal_encoding('UTF-8');

// Errors are logged, never shown to visitors unless APP_DEBUG is true.
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
