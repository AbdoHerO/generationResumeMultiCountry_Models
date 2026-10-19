<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * A user's global profile: CV content in French, English and Chinese, reused to fill new CVs.
 */
final class Profile
{
    /** @return array{data: array<string, mixed>, version: int, updated_at: ?string}|null */
    public static function find(int $userId): ?array
    {
        $row = Database::one('SELECT data_json, version, updated_at FROM profiles WHERE user_id = ?', [$userId]);
        if (!$row) {
            return null;
        }
        $data = json_decode((string) $row['data_json'], true);
        return ['data' => is_array($data) ? $data : [], 'version' => (int) $row['version'], 'updated_at' => $row['updated_at']];
    }

    /**
     * Saves the profile with optimistic concurrency. Returns the new version, or null when the
     * stored version is not the one the client edited.
     * @param array<string, mixed> $data
     */
    public static function save(int $userId, array $data, ?int $expectedVersion): ?int
    {
        $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE);
        $current = Database::value('SELECT version FROM profiles WHERE user_id = ?', [$userId]);
        if ($current === null) {
            Database::query('INSERT INTO profiles (user_id, data_json, version) VALUES (?, ?, 1)', [$userId, $json]);
            return 1;
        }
        if ($expectedVersion !== null && (int) $current !== $expectedVersion) {
            return null;
        }
        $stmt = Database::query('UPDATE profiles SET data_json = ?, version = version + 1 WHERE user_id = ? AND version = ?', [$json, $userId, (int) $current]);
        return $stmt->rowCount() === 1 ? (int) $current + 1 : null;
    }
}
