<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Private storage under storage/ (never web-accessible). Directories are created on demand,
 * because a fresh deployment may link an empty shared storage directory.
 */
final class Storage
{
    public static function root(): string
    {
        return (string) (config('app.storage_path') ?: BASE_PATH . '/storage');
    }

    public static function path(string $relative = ''): string
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');
        if (str_contains($relative, '..')) {
            throw new \InvalidArgumentException('Invalid storage path');
        }
        $path = self::root() . ($relative !== '' ? '/' . $relative : '');
        if (!is_dir($path)) {
            @mkdir($path, 0770, true);
        }
        return $path;
    }

    public static function file(string $dir, string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $name) || str_contains($name, '..')) {
            throw new \InvalidArgumentException('Invalid file name');
        }
        return self::path($dir) . '/' . $name;
    }
}
