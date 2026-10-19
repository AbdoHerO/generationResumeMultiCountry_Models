<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return new self($body, $status, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /** Consistent success envelope for JSON endpoints. @param array<string, mixed> $data */
    public static function ok(array $data = [], string $message = ''): self
    {
        return self::json(['ok' => true, 'message' => $message, 'data' => $data]);
    }

    /** Consistent error envelope for JSON endpoints. @param array<string, mixed> $errors */
    public static function error(string $message, int $status = 400, array $errors = [], string $code = ''): self
    {
        return self::json(['ok' => false, 'message' => $message, 'code' => $code, 'errors' => (object) $errors], $status);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    public static function file(string $contents, string $mime, string $filename, bool $inline = false): self
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'download';
        $disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
        return new self($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
        echo $this->body;
    }
}
