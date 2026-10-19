<?php

declare(strict_types=1);

namespace Tests\Http;

/** A tiny cookie-keeping HTTP client for end-to-end tests against the built-in server. */
final class Client
{
    private string $jar;
    public string $csrf = '';

    public function __construct(private string $base)
    {
        $this->jar = (string) tempnam(sys_get_temp_dir(), 'jar');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /**
     * @param array<string, mixed>|string|null $body form fields, or a raw JSON string with $json = true
     * @return array{status: int, headers: array<string, string>, body: string, location: string}
     */
    public function request(string $method, string $path, array|string|null $body = null, bool $json = false, array $headers = []): array
    {
        $ch = curl_init($this->base . $path);
        $h = ['Accept: ' . ($json ? 'application/json' : 'text/html')];
        if ($json) {
            $h[] = 'Content-Type: application/json';
            $h[] = 'X-CSRF-Token: ' . $this->csrf;
        }
        foreach ($headers as $k => $v) {
            $h[] = "$k: $v";
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 60,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) && !$this->hasFile($body) ? http_build_query($body) : $body);
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headers = [];
        foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }
        $res = ['status' => $status, 'headers' => $headers, 'body' => substr($raw, $size), 'location' => $headers['location'] ?? ''];
        if (preg_match('/name="csrf-token" content="([^"]+)"/', $res['body'], $m)) {
            $this->csrf = $m[1];
        }
        return $res;
    }

    /** @param array<string, mixed> $a */
    private function hasFile(array $a): bool
    {
        foreach ($a as $v) {
            if ($v instanceof \CURLFile) {
                return true;
            }
        }
        return false;
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** Form POST with the current CSRF token (fetches a page first when none is known). */
    public function post(string $path, array $fields = [], bool $withToken = true): array
    {
        if ($withToken && $this->csrf === '') {
            $this->get('/login');
        }
        return $this->request('POST', $path, $withToken ? $fields + ['_token' => $this->csrf] : $fields);
    }

    /** JSON API call; returns the decoded envelope plus the status. */
    public function api(string $path, mixed $payload = []): array
    {
        $r = $this->request('POST', $path, (string) json_encode($payload), true);
        return ['status' => $r['status']] + (json_decode($r['body'], true) ?: []);
    }

    public function apiGet(string $path): array
    {
        $r = $this->request('GET', $path, null, true);
        return ['status' => $r['status']] + (json_decode($r['body'], true) ?: []);
    }

    public function upload(string $path, string $field, string $file, string $mime): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-CSRF-Token: ' . $this->csrf],
            CURLOPT_POSTFIELDS => [$field => new \CURLFile($file, $mime, 'photo')],
        ]);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status] + (json_decode($body, true) ?: []);
    }
}
