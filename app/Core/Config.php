<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Reads config/*.php files lazily, with dot notation: config('app.name').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $loaded = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);
        if (!array_key_exists($file, self::$loaded)) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            self::$loaded[$file] = is_file($path) ? require $path : [];
        }
        $value = self::$loaded[$file];
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** For tests: forget cached files so env changes are picked up. */
    public static function reset(): void
    {
        self::$loaded = [];
    }
}
