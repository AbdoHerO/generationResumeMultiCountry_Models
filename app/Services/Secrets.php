<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Encryption of secrets stored in the database (such as the Groq API token), with libsodium's
 * authenticated secretbox. The key is derived from APP_KEY, so a database dump alone does not
 * reveal the secrets.
 */
final class Secrets
{
    private const PREFIX = 'v1:';

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    /** The plain text, or null when the value is missing, altered or encrypted with another key. */
    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || !str_starts_with($stored, self::PREFIX)) {
            return null;
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());
        return $plain === false ? null : $plain;
    }

    /** "gsk_…a1b2": enough to recognise a token, never enough to use it. */
    public static function mask(string $secret): string
    {
        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('•', 6);
        }
        return substr($secret, 0, 4) . '…' . substr($secret, -4);
    }

    private static function key(): string
    {
        return sodium_crypto_generichash('resumeatlas|settings-secrets|' . Auth::appKey(), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
