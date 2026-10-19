<?php

declare(strict_types=1);

namespace App\Core;

/**
 * JSON-lines log in storage/logs. Never pass secrets, passwords or CV content here.
 */
final class Logger
{
    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private static function write(string $level, string $message, array $context): void
    {
        try {
            $line = json_encode([
                'time' => gmdate('c'),
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $file = Storage::path('logs') . '/app-' . gmdate('Y-m-d') . '.log';
            @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            error_log("[$level] $message");
        }
    }
}
