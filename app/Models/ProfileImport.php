<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * A PDF import awaiting review. Every query is scoped by user_id: another user's import is invisible.
 */
final class ProfileImport
{
    /** @return array<string, mixed>|null */
    public static function findOwned(int $id, int $userId): ?array
    {
        $row = Database::one('SELECT * FROM profile_imports WHERE id = ? AND user_id = ?', [$id, $userId]);
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['version'] = (int) $row['version'];
        $row['data'] = json_decode((string) ($row['data_json'] ?? ''), true) ?: [];
        unset($row['data_json']);
        return $row;
    }

    public static function create(int $userId, string $fileName): int
    {
        Database::query('INSERT INTO profile_imports (user_id, file_name, status) VALUES (?, ?, ?)', [$userId, mb_substr($fileName, 0, 190), 'processing']);
        return Database::lastId();
    }

    /** @param array<string, mixed> $data */
    public static function store(int $id, int $userId, array $data, string $status, ?string $sourceLanguage = null): void
    {
        Database::query(
            'UPDATE profile_imports SET data_json = ?, status = ?, source_language = COALESCE(?, source_language), error = NULL, version = version + 1 WHERE id = ? AND user_id = ?',
            [json_encode($data, JSON_UNESCAPED_UNICODE), $status, $sourceLanguage, $id, $userId]
        );
    }

    /**
     * Review edits, with optimistic concurrency. @param array<string, mixed> $data
     */
    public static function saveEdits(int $id, int $userId, array $data, int $expectedVersion): ?int
    {
        $stmt = Database::query(
            'UPDATE profile_imports SET data_json = ?, version = version + 1 WHERE id = ? AND user_id = ? AND version = ? AND status = ?',
            [json_encode($data, JSON_UNESCAPED_UNICODE), $id, $userId, $expectedVersion, 'ready']
        );
        return $stmt->rowCount() === 1 ? $expectedVersion + 1 : null;
    }

    public static function fail(int $id, int $userId, string $error): void
    {
        Database::query('UPDATE profile_imports SET status = ?, error = ? WHERE id = ? AND user_id = ?', ['failed', mb_substr($error, 0, 500), $id, $userId]);
    }

    public static function setStatus(int $id, int $userId, string $status): void
    {
        Database::query('UPDATE profile_imports SET status = ? WHERE id = ? AND user_id = ?', [$status, $id, $userId]);
    }

    public static function delete(int $id, int $userId): void
    {
        Database::query('DELETE FROM profile_imports WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function countRecent(int $userId, int $minutes): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM profile_imports WHERE user_id = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL ? MINUTE)', [$userId, $minutes]);
    }

    /** Imports are temporary: anything older than a week is removed. */
    public static function purgeOld(): void
    {
        Database::query('DELETE FROM profile_imports WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 7 DAY)');
    }

    /** @return list<array<string, mixed>> the user's recent imports (no content) */
    public static function recentForUser(int $userId, int $limit = 5): array
    {
        return Database::all('SELECT id, status, source_language, file_name, error, created_at FROM profile_imports WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, min(20, $limit)), [$userId]);
    }
}
