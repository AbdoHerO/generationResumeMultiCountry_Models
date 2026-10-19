<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Key/value application settings stored as JSON (administrator-managed).
 */
final class Setting
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public static function get(string $name, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::all('SELECT name, value FROM settings') as $row) {
                    self::$cache[$row['name']] = json_decode((string) $row['value'], true);
                }
            } catch (\Throwable) {
                // Before the first migration the table does not exist yet.
            }
        }
        return array_key_exists($name, self::$cache) ? self::$cache[$name] : $default;
    }

    public static function set(string $name, mixed $value): void
    {
        Database::query(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, json_encode($value, JSON_UNESCAPED_UNICODE)]
        );
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
