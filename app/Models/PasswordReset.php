<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Password reset tokens. Only a SHA-256 hash of the token is stored; tokens expire after 60
 * minutes and are single-use.
 */
final class PasswordReset
{
    public static function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        Database::query('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
        Database::query(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 60 MINUTE)',
            [$userId, hash('sha256', $token)]
        );
        return $token;
    }

    /** @return array<string, mixed>|null */
    public static function findValid(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        return Database::one(
            'SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $token)]
        );
    }

    public static function consume(int $id): void
    {
        Database::query('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }
}
