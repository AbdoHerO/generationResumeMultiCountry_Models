<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;

/**
 * Session authentication. The current user is re-read from the database on every request, so
 * a deactivated account or a demoted administrator loses access immediately.
 */
final class Auth
{
    /** @var array<string, mixed>|null|false false = not loaded yet */
    private static array|null|false $user = false;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$user === false) {
            self::$user = null;
            $id = (int) Session::get('user_id', 0);
            if ($id > 0) {
                $user = User::find($id);
                $token = (string) Session::get('auth_stamp', '');
                if ($user && $user['status'] === 'active' && hash_equals(self::stamp($id), $token)) {
                    self::$user = $user;
                } else {
                    Session::forget('user_id');
                    Session::forget('auth_stamp');
                }
            }
        }
        return self::$user;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * @return array{ok: bool, error?: string, throttled?: bool, user?: array<string, mixed>}
     */
    public static function attempt(string $email, string $password, Request $request): array
    {
        $email = User::normalizeEmail($email);
        $ip = $request->ip();
        if (LoginAttempt::tooManyLogins($email, $ip)) {
            return ['ok' => false, 'throttled' => true, 'error' => 'Too many failed attempts. Please wait 15 minutes and try again.'];
        }
        $user = User::findForAuth($email);
        // Verify against a dummy hash when the account does not exist, to keep timing similar.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $valid = password_verify($password, (string) $hash);
        if (!$user || !$valid) {
            LoginAttempt::record($email, $ip, false);
            return ['ok' => false, 'error' => 'The e-mail address or password is incorrect.'];
        }
        if ($user['status'] !== 'active') {
            LoginAttempt::record($email, $ip, false);
            AuditLog::record((int) $user['id'], 'auth.login_blocked_inactive', 'user', (int) $user['id'], [], $ip);
            return ['ok' => false, 'error' => 'This account has been deactivated. Contact the administrator.'];
        }
        User::rehashIfNeeded((int) $user['id'], $password, (string) $user['password_hash']);
        LoginAttempt::record($email, $ip, true);
        LoginAttempt::clearFailures($email);
        self::login((int) $user['id']);
        User::touchLogin((int) $user['id']);
        if ($user['role'] === 'admin') {
            AuditLog::record((int) $user['id'], 'auth.admin_login', 'user', (int) $user['id'], [], $ip);
        }
        unset($user['password_hash']);
        return ['ok' => true, 'user' => $user];
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::put('user_id', $userId);
        Session::put('auth_stamp', self::stamp($userId));
        self::$user = false;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = false;
    }

    /**
     * Binds the session to the account's current password hash: changing the password signs out
     * every other session of that account.
     */
    private static function stamp(int $userId): string
    {
        $hash = User::passwordHash($userId) ?? '';
        return hash_hmac('sha256', $userId . '|' . $hash, self::appKey());
    }

    public static function appKey(): string
    {
        $key = (string) config('app.key', '');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = $decoded === false ? $key : $decoded;
        }
        if ($key === '') {
            // No APP_KEY: derive a stable per-installation secret kept in private storage.
            $file = \App\Core\Storage::path('cache') . '/app.key';
            if (!is_file($file)) {
                @file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX);
                @chmod($file, 0600);
            }
            $key = (string) @file_get_contents($file);
        }
        return $key;
    }

    /** Forget the per-request cache (tests). */
    public static function reset(): void
    {
        self::$user = false;
    }
}
