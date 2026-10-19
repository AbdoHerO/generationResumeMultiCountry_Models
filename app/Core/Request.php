<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, string> */
    public array $params = [];

    /** @var array<string, mixed>|null */
    private ?array $json = null;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $server,
        public readonly array $files,
        public readonly array $cookies,
        private readonly string $rawBody = '',
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = '/' . trim(rawurldecode($path), '/');
        $raw = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? (string) file_get_contents('php://input') : '';
        return new self($method, $path, $_GET, $_POST, $_SERVER, $_FILES, $_COOKIE, $raw);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $json = $this->json();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        return $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if ($this->json === null) {
            $this->json = [];
            if ($this->rawBody !== '' && str_contains($this->header('Content-Type'), 'application/json')) {
                $decoded = json_decode($this->rawBody, true, 64);
                $this->json = is_array($decoded) ? $decoded : [];
            }
        }
        return $this->json;
    }

    public function rawBodyLength(): int
    {
        return strlen($this->rawBody);
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type') {
            return (string) ($this->server['CONTENT_TYPE'] ?? $this->server[$key] ?? '');
        }
        return (string) ($this->server[$key] ?? '');
    }

    public function param(string $name): string
    {
        return $this->params[$name] ?? '';
    }

    public function isJson(): bool
    {
        return str_contains($this->header('Accept'), 'application/json')
            || str_contains($this->header('Content-Type'), 'application/json')
            || str_starts_with($this->path, '/api/');
    }

    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if ($this->fromTrustedProxy()) {
            $forwarded = trim(explode(',', $this->header('X-Forwarded-For'))[0] ?? '');
            $cf = $this->header('CF-Connecting-IP');
            foreach ([$cf, $forwarded] as $candidate) {
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
        return $remote;
    }

    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if ((int) ($this->server['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        return $this->fromTrustedProxy() && strtolower($this->header('X-Forwarded-Proto')) === 'https';
    }

    private function fromTrustedProxy(): bool
    {
        $trusted = array_filter(array_map('trim', explode(',', (string) config('app.trusted_proxies', ''))));
        return in_array((string) ($this->server['REMOTE_ADDR'] ?? ''), $trusted, true);
    }

    public function userAgent(): string
    {
        return mb_substr($this->header('User-Agent'), 0, 255);
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        return is_array($f) && isset($f['tmp_name']) && !is_array($f['tmp_name']) ? $f : null;
    }
}
