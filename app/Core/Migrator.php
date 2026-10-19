<?php

declare(strict_types=1);

namespace App\Core;

use Database\Seeders\AdminSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\TemplateSeeder;

/**
 * Applies database/migrations/*.sql in order (each once, recorded in schema_migrations),
 * then runs the idempotent seeders. Safe to run repeatedly and from several PHP workers
 * at once (MySQL named lock).
 *
 * ensureCurrent() is cheap: it compares a signature of the migration files, the seeders'
 * version and the template definitions with storage/cache/schema.sig and does nothing
 * when they match.
 */
final class Migrator
{
    /** Bump when a seeder changes behaviour. */
    public const SEED_VERSION = 3;

    public static function ensureCurrent(): void
    {
        $sig = self::signature();
        $cache = Storage::path('cache') . '/schema.sig';
        if (is_file($cache) && trim((string) file_get_contents($cache)) === $sig) {
            return;
        }
        self::migrate();
        self::seed();
        @file_put_contents($cache, $sig, LOCK_EX);
    }

    public static function signature(): string
    {
        $parts = [self::SEED_VERSION, (string) config('database.database')];
        foreach (self::files() as $file) {
            $parts[] = basename($file) . ':' . md5_file($file);
        }
        $parts[] = md5_file(BASE_PATH . '/config/templates.php');
        $parts[] = (string) config('app.admin.email');
        return hash('sha256', implode('|', $parts));
    }

    /** @return list<string> */
    public static function files(): array
    {
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    /** @return list<string> applied versions */
    public static function migrate(?callable $log = null): array
    {
        $pdo = Database::pdo();
        $locked = (int) Database::value('SELECT GET_LOCK(?, 60)', ['resumeatlas_migrations']) === 1;
        if (!$locked) {
            throw new \RuntimeException('Could not acquire the migration lock.');
        }
        $applied = [];
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(120) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $done = array_column(Database::all('SELECT version FROM schema_migrations'), 'version');
            foreach (self::files() as $file) {
                $version = basename($file, '.sql');
                if (in_array($version, $done, true)) {
                    continue;
                }
                $sql = (string) file_get_contents($file);
                $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
                foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $statement) {
                    if (trim($statement) !== '') {
                        $pdo->exec($statement);
                    }
                }
                Database::query('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
                $applied[] = $version;
                if ($log) {
                    $log("Migrated: $version");
                }
            }
        } finally {
            Database::query('SELECT RELEASE_LOCK(?)', ['resumeatlas_migrations']);
        }
        if ($applied !== []) {
            Logger::info('Migrations applied', ['versions' => $applied]);
        }
        return $applied;
    }

    public static function seed(?callable $log = null): void
    {
        $n = TemplateSeeder::run();
        AdminSeeder::run();
        SettingsSeeder::run();
        if ($log) {
            $log("Seeded: $n templates checked, administrator ensured, settings ensured");
        }
    }
}
