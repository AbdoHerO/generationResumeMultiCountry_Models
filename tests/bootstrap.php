<?php

declare(strict_types=1);

/*
 * Test bootstrap: loads the application and prepares the `cvbuilder_test` database
 * (created if missing, migrated and seeded). The connection settings come from .env; only the
 * database name is overridden by phpunit.xml, so tests never touch the development data.
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Migrator;

$name = (string) config('database.database');
if (!preg_match('/_test$/', $name)) {
    fwrite(STDERR, "Refusing to run tests against database '$name' (the name must end with _test).\n");
    exit(1);
}

try {
    $socket = (string) config('database.socket', '');
    $dsn = $socket !== ''
        ? sprintf('mysql:unix_socket=%s;charset=utf8mb4', $socket)
        : sprintf('mysql:host=%s;port=%d;charset=utf8mb4', config('database.host'), (int) config('database.port'));
    $pdo = new PDO($dsn, (string) config('database.username'), (string) config('database.password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    Migrator::migrate();
    Migrator::seed();
    define('TEST_DB_READY', true);
} catch (Throwable $e) {
    fwrite(STDERR, 'Test database unavailable: ' . $e->getMessage() . "\nDatabase-backed tests will be skipped.\n");
    define('TEST_DB_READY', false);
}
