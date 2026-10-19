<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened PHP session: strict mode, HttpOnly + SameSite=Lax cookie, Secure on HTTPS,
 * idle timeout, CSRF token and flash messages.
 */
final class Session
{
    private static bool $started = false;

    public static function start(Request $request): void
    {
        if (self::$started || PHP_SAPI === 'cli' && !defined('TESTING_HTTP')) {
            self::$started = true;
            if (session_status() !== PHP_SESSION_ACTIVE && !isset($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }
        $secureSetting = (string) config('app.session.secure', 'auto');
        $secure = $secureSetting === 'auto' ? $request->isSecure() : filter_var($secureSetting, FILTER_VALIDATE_BOOL);

        $dir = Storage::path('sessions');
        ini_set('session.save_path', $dir);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');
        ini_set('session.gc_maxlifetime', (string) (self::lifetimeSeconds() + 600));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        session_name((string) config('app.session.name', 'resumeatlas_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;

        $now = time();
        $last = (int) ($_SESSION['_last_activity'] ?? $now);
        if (isset($_SESSION['user_id']) && $now - $last > self::lifetimeSeconds()) {
            // Idle timeout: keep nothing but a notice.
            $_SESSION = [];
            session_regenerate_id(true);
            self::flash('info', 'Your session expired after a period of inactivity. Please sign in again.');
        }
        $_SESSION['_last_activity'] = $now;
    }

    public static function lifetimeSeconds(): int
    {
        return max(5, (int) config('app.session.lifetime_minutes', 120)) * 60;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($_SESSION['_csrf']);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function validCsrf(string $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? '';
        return is_string($expected) && $expected !== '' && $token !== '' && hash_equals($expected, $token);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public static function pullFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($messages) ? $messages : [];
    }

    /** @param array<string, mixed> $old */
    public static function flashInput(array $old): void
    {
        unset($old['password'], $old['password_confirmation'], $old['current_password'], $old['_token']);
        $_SESSION['_old'] = $old;
    }

    /** @param array<string, string> $errors */
    public static function flashErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }
}
