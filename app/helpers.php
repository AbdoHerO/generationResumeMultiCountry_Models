<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;
use App\Core\Session;

function env(string $key, ?string $default = null): ?string
{
    return Env::get($key, $default);
}

function env_bool(string $key, bool $default = false): bool
{
    $v = Env::get($key);
    return $v === null ? $default : filter_var($v, FILTER_VALIDATE_BOOL);
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** Escape for HTML text and attributes. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Application-relative URL (the app may live in a sub-folder under XAMPP). */
function url(string $path = '/'): string
{
    $base = rtrim((string) config('app.base_path', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** Versioned public asset URL for cache busting. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file) . filesize($file)), 0, 8) : '0';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function csrf_token(): string
{
    return Session::csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** Inline Lucide icon. */
function icon(string $name, int $size = 18, string $class = ''): string
{
    static $icons = null;
    $icons ??= require BASE_PATH . '/config/icons.php';
    $inner = $icons[$name] ?? $icons['circle-help'] ?? '';
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" width="' . $size . '" height="' . $size
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $inner . '</svg>';
}

/** Previous form input after a failed submission. */
function old(string $key, string $default = ''): string
{
    $old = App\Core\View::shared()['_old'] ?? [];
    $v = $old[$key] ?? $default;
    return is_scalar($v) ? (string) $v : $default;
}

function field_error(string $key): string
{
    $errors = App\Core\View::shared()['_errors'] ?? [];
    return isset($errors[$key]) ? (string) $errors[$key] : '';
}

/** @return array<string, mixed>|null */
function auth_user(): ?array
{
    return App\Services\Auth::user();
}

function is_admin(): bool
{
    return (auth_user()['role'] ?? '') === 'admin';
}

function app_name(): string
{
    return (string) App\Models\Setting::get('app_name', (string) config('app.name', 'ResumeAtlas'));
}

/** Human date for the interface (not for CVs). */
function ui_date(?string $datetime, bool $withTime = false): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime . ' UTC');
    return $ts === false ? '—' : gmdate($withTime ? 'M j, Y · H:i' : 'M j, Y', $ts);
}

/** "3 minutes ago" style relative time. */
function ui_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $diff = time() - (int) strtotime($datetime . ' UTC');
    return match (true) {
        $diff < 60 => 'just now',
        $diff < 3600 => (int) floor($diff / 60) . ' min ago',
        $diff < 86400 => (int) floor($diff / 3600) . ' h ago',
        $diff < 86400 * 7 => (int) floor($diff / 86400) . ' d ago',
        default => ui_date($datetime),
    };
}

/** Safe JSON for <script type="application/json"> blocks. */
function json_script(mixed $data): string
{
    return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
}

function flag(string $country): string
{
    return '<img class="flag" src="' . e(asset('img/flags/' . strtolower($country) . '.svg')) . '" alt="" width="24" height="16">';
}
