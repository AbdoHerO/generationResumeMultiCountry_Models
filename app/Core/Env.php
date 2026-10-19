<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader. Real environment variables always win over the file.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }
            $len = strlen($value);
            if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            } elseif (($pos = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $pos));
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }
        if (array_key_exists($key, self::$values) && self::$values[$key] !== '') {
            return self::$values[$key];
        }
        return $default;
    }

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }
}
