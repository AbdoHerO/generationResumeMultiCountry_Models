<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Core\Logger;
use App\Models\Setting;
use App\Services\Secrets;

/**
 * Client for Groq's OpenAI-compatible chat completions API (GROQ_API_URL).
 *
 * The API token, model and reasoning effort are administrator settings. Settings are cached in
 * memory for the duration of one request only; every call reloads them first, so a token changed
 * in the administration is used by the very next call, without restarting anything.
 * The token never appears in logs, errors or responses.
 */
final class GroqClient
{
    public const SETTING_KEY = 'ai_api_key';
    public const SETTING_MODEL = 'ai_model';
    public const SETTING_REASONING = 'ai_reasoning_effort';
    public const SETTING_ENABLED = 'ai_import_enabled';
    public const REASONING = ['none', 'default'];

    /** @return array{url: string, model: string, reasoning: string, enabled: bool, key: ?string, keySource: string} */
    public static function settings(): array
    {
        Setting::flush(); // reload from the database: a new token applies immediately
        $stored = Setting::get(self::SETTING_KEY);
        $key = is_string($stored) ? Secrets::decrypt($stored) : null;
        $source = $key !== null ? 'admin' : 'none';
        if ($key === null && config('app.ai.env_key', '') !== '') {
            $key = (string) config('app.ai.env_key');
            $source = 'env';
        }
        $model = trim((string) Setting::get(self::SETTING_MODEL, ''));
        $reasoning = (string) Setting::get(self::SETTING_REASONING, 'none');
        return [
            'url' => (string) config('app.ai.url'),
            'model' => $model !== '' ? $model : (string) config('app.ai.model'),
            'reasoning' => in_array($reasoning, self::REASONING, true) ? $reasoning : 'none',
            'enabled' => (bool) Setting::get(self::SETTING_ENABLED, true),
            'key' => $key,
            'keySource' => $source,
        ];
    }

    /** Output tokens allowed per request (GROQ_MAX_TOKENS; 950 suits Groq's free tier). */
    public static function maxTokens(): int
    {
        return max(256, (int) config('app.ai.max_tokens', 950));
    }

    /** True when the import feature can be used (enabled and a token is configured). */
    public static function available(): bool
    {
        $s = self::settings();
        return $s['enabled'] && $s['key'] !== null && $s['key'] !== '';
    }

    /**
     * Sends a system + user prompt and returns the decoded JSON object of the answer.
     * @return array<string, mixed>
     * @throws AiException
     */
    public function json(string $system, string $user, ?int $maxTokens = null, ?float $deadline = null): array
    {
        $text = $this->chat([['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]], true, $maxTokens, $deadline);
        return self::decodeJson($text);
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @throws AiException
     */
    public function chat(array $messages, bool $jsonMode = false, ?int $maxTokens = null, ?float $deadline = null): string
    {
        $s = self::settings();
        if ($s['key'] === null || $s['key'] === '') {
            throw new AiException('The CV import is not configured yet: an administrator must add the Groq API token.', 'not_configured');
        }
        $body = [
            'model' => $s['model'],
            'messages' => $messages,
            'temperature' => 0.2,
            'top_p' => 0.95,
            // Never above the configured cap: Groq refuses a request whose max_tokens exceeds the
            // account's output-tokens-per-minute limit (1000 on the free tier for some models).
            'max_completion_tokens' => min($maxTokens ?? PHP_INT_MAX, self::maxTokens()),
            'stream' => false,
        ];
        $optional = [];
        if ($jsonMode) {
            $optional['response_format'] = ['type' => 'json_object'];
        }
        if (str_starts_with(strtolower($s['model']), 'qwen/')) {
            // Qwen 3 models think before answering; hide the reasoning, and skip it unless asked.
            $optional['reasoning_effort'] = $s['reasoning'];
            $optional['reasoning_format'] = 'hidden';
        }

        $deadline ??= microtime(true) + (int) config('app.ai.timeout', 50);
        [$status, $response, $wait] = $this->post($s['url'], $s['key'], $body + $optional, $deadline);
        if ($status === 400 && $optional !== [] && preg_match('/response_format|reasoning|json/i', (string) ($response['error']['message'] ?? ''))) {
            // The model does not support an optional parameter: retry with the plain request.
            $optional = [];
            [$status, $response, $wait] = $this->post($s['url'], $s['key'], $body, $deadline);
        }
        if ($status === 429 && $wait > 0 && $wait <= 12 && microtime(true) + $wait + 25 < $deadline) {
            // A short per-minute limit: wait for it within this request, then retry once.
            usleep((int) (($wait + 0.5) * 1e6));
            [$status, $response, $wait] = $this->post($s['url'], $s['key'], $body + $optional, $deadline);
        }
        if ($status < 200 || $status >= 300) {
            throw self::httpError($status, $response, $wait);
        }
        $choice = $response['choices'][0] ?? null;
        $content = is_array($choice) ? (string) ($choice['message']['content'] ?? '') : '';
        if (($choice['finish_reason'] ?? '') === 'length') {
            throw new AiException('The answer was cut off (too long for the output limit).', 'too_long');
        }
        $content = trim((string) preg_replace('#<think>.*?</think>#s', '', $content));
        if ($content === '') {
            throw new AiException('The AI service returned an empty answer. Please try again.', 'empty');
        }
        return $content;
    }

    /**
     * A tiny request that proves the URL, token and model work.
     * @return array{ok: bool, message: string, model: string, ms: int}
     */
    public static function testConnection(): array
    {
        $started = microtime(true);
        $model = self::settings()['model'];
        try {
            $answer = (new self())->chat([['role' => 'user', 'content' => 'Reply with the single word: pong']], false, 512);
            return ['ok' => true, 'message' => 'Connected. The model answered: ' . mb_substr(trim($answer), 0, 60), 'model' => $model, 'ms' => (int) round(1000 * (microtime(true) - $started))];
        } catch (AiException $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'model' => $model, 'ms' => (int) round(1000 * (microtime(true) - $started))];
        }
    }

    /**
     * @param array<string, mixed> $body
     * @return array{0: int, 1: array<string, mixed>, 2: float} status, decoded body, seconds to wait when rate limited
     * @throws AiException
     */
    private function post(string $url, string $key, array $body, float $deadline): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new AiException('GROQ_API_URL is not a valid address.', 'config');
        }
        $headers = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers): int {
                if (preg_match('/^(retry-after|x-ratelimit-reset-tokens|x-ratelimit-reset-requests):\s*(\S+)/i', trim($line), $m)) {
                    $headers[strtolower($m[1])] = $m[2];
                }
                return strlen($line);
            },
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $key],
            CURLOPT_POSTFIELDS => (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(5, min((int) config('app.ai.timeout', 50), (int) floor($deadline - microtime(true)))),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($raw === false || $errno !== 0) {
            Logger::warning('AI request failed', ['curl_errno' => $errno]);
            throw new AiException($errno === CURLE_OPERATION_TIMEDOUT
                ? 'The AI service took too long to answer. Please try again; a shorter PDF also helps.'
                : 'The AI service could not be reached. Please try again in a moment.', 'network');
        }
        $decoded = json_decode((string) $raw, true);
        $wait = 0.0;
        if ($status === 429) {
            foreach (['retry-after', 'x-ratelimit-reset-tokens', 'x-ratelimit-reset-requests'] as $h) {
                if (isset($headers[$h]) && ($w = self::seconds($headers[$h])) > 0) {
                    $wait = $w;
                    break;
                }
            }
        }
        return [$status, is_array($decoded) ? $decoded : [], $wait];
    }

    /** "7", "53.16s", "1m2.5s" or "120ms" → seconds. */
    public static function seconds(string $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $total = 0.0;
        if (preg_match_all('/(\d+(?:\.\d+)?)(ms|h|m|s)/', $value, $m, PREG_SET_ORDER)) {
            foreach ($m as [, $n, $unit]) {
                $total += (float) $n * ['ms' => 0.001, 's' => 1, 'm' => 60, 'h' => 3600][$unit];
            }
        }
        return $total;
    }

    /** @param array<string, mixed> $response */
    private static function httpError(int $status, array $response, float $wait = 0.0): AiException
    {
        // Groq's message explains limits ("Limit 8000, Used 5200, Requested 3100"); the organisation id is removed.
        $detail = mb_substr((string) preg_replace('/org_[A-Za-z0-9]+/', 'org_…', (string) ($response['error']['message'] ?? '')), 0, 400);
        Logger::warning('AI request refused', ['status' => $status, 'code' => (string) ($response['error']['code'] ?? ''), 'detail' => $detail, 'wait' => $wait]);
        return match (true) {
            $status === 401, $status === 403 => new AiException('The Groq API token was refused. An administrator must update it in Settings.', 'unauthorized'),
            $status === 404 => new AiException('The AI model was not found. An administrator must check the model name in Settings.', 'model'),
            $status === 413 => new AiException('This CV is too long for the AI service. Try a shorter PDF.', 'too_long'),
            $status === 429 => new AiException('The AI service quota is used up for the moment. Retrying shortly…', 'rate_limited', (int) ceil(max(5.0, min(90.0, $wait ?: 30.0)))),
            $status >= 500 => new AiException('The AI service is temporarily unavailable. Please try again later.', 'unavailable'),
            default => new AiException('The AI service refused the request (HTTP ' . $status . ').', 'refused'),
        };
    }

    /**
     * Extracts the JSON object from a model answer (tolerates code fences and surrounding text).
     * @return array<string, mixed>
     * @throws AiException
     */
    public static function decodeJson(string $text): array
    {
        $text = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text));
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            throw new AiException('The AI answer could not be read. Please try again.', 'bad_json');
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (!is_array($data)) {
            throw new AiException('The AI answer could not be read. Please try again.', 'bad_json');
        }
        return $data;
    }
}
