<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Throttling for sign-in, registration and password-reset requests.
 */
final class LoginAttempt
{
    public static function record(string $email, string $ip, bool $succeeded, string $kind = 'login'): void
    {
        Database::query(
            'INSERT INTO login_attempts (email, ip, kind, succeeded) VALUES (?, ?, ?, ?)',
            [mb_substr(strtolower(trim($email)), 0, 190), $ip, $kind, $succeeded ? 1 : 0]
        );
        if (random_int(1, 50) === 1) {
            Database::query('DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 2 DAY');
        }
    }

    /** True when the e-mail or the IP has too many recent failed sign-ins. */
    public static function tooManyLogins(string $email, string $ip): bool
    {
        $max = (int) config('app.limits.login_max_attempts', 5);
        $window = (int) config('app.limits.login_window_minutes', 15);
        $byEmail = (int) Database::value(
            "SELECT COUNT(*) FROM login_attempts WHERE email = ? AND kind = 'login' AND succeeded = 0
             AND attempted_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE",
            [strtolower(trim($email)), $window]
        );
        $byIp = (int) Database::value(
            "SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND kind = 'login' AND succeeded = 0
             AND attempted_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE",
            [$ip, $window]
        );
        return $byEmail >= $max || $byIp >= $max * 4;
    }

    public static function tooMany(string $ip, string $kind, int $max, int $minutes): bool
    {
        $n = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND kind = ? AND attempted_at > UTC_TIMESTAMP() - INTERVAL ? MINUTE',
            [$ip, $kind, $minutes]
        );
        return $n >= $max;
    }

    public static function clearFailures(string $email): void
    {
        Database::query("DELETE FROM login_attempts WHERE email = ? AND kind = 'login' AND succeeded = 0", [strtolower(trim($email))]);
    }
}
