<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * CVs. Every read and write that comes from a user request is scoped by user_id here, so a
 * changed ID in a URL can never reach another account's CV.
 */
final class Cv
{
    private const LIST_COLUMNS = 'c.id, c.user_id, c.title, c.country_code, c.language_code, c.template_id, c.status,
        c.version, c.photo_path, c.created_at, c.updated_at, t.name AS template_name, t.slug AS template_slug';

    /** @return array<string, mixed>|null */
    public static function findOwned(int $id, int $userId): ?array
    {
        $row = Database::one(
            'SELECT c.*, t.name AS template_name, t.slug AS template_slug
             FROM cvs c JOIN templates t ON t.id = c.template_id WHERE c.id = ? AND c.user_id = ?',
            [$id, $userId]
        );
        return self::hydrate($row);
    }

    /**
     * @param array{q?: string, country?: string, template?: string, status?: string, sort?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId, array $filters = []): array
    {
        $where = ['c.user_id = :uid'];
        $params = ['uid' => $userId];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = 'c.title LIKE :q';
            $params['q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        }
        if (!empty($filters['country']) && preg_match('/^[A-Z]{2}$/', (string) $filters['country'])) {
            $where[] = 'c.country_code = :country';
            $params['country'] = $filters['country'];
        }
        if (!empty($filters['template']) && preg_match('/^[a-z0-9-]{1,80}$/', (string) $filters['template'])) {
            $where[] = 't.slug = :tpl';
            $params['tpl'] = $filters['template'];
        }
        if (in_array($filters['status'] ?? '', ['draft', 'completed'], true)) {
            $where[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }
        $order = match ($filters['sort'] ?? 'updated') {
            'created' => 'c.created_at DESC, c.id DESC',
            'oldest' => 'c.updated_at ASC, c.id ASC',
            'title' => 'c.title ASC, c.id ASC',
            default => 'c.updated_at DESC, c.id DESC',
        };
        return Database::all(
            'SELECT ' . self::LIST_COLUMNS . ' FROM cvs c JOIN templates t ON t.id = c.template_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order,
            $params
        );
    }

    public static function countForUser(int $userId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM cvs WHERE user_id = ?', [$userId]);
    }

    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $design
     */
    public static function create(int $userId, string $title, string $country, string $language, int $templateId, array $content, array $design, string $status = 'draft'): int
    {
        Database::query(
            'INSERT INTO cvs (user_id, title, country_code, language_code, template_id, content_json, design_settings_json, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $title, $country, $language, $templateId, self::encode($content), self::encode($design), $status]
        );
        return Database::lastId();
    }

    /**
     * Optimistic update: succeeds only if the stored version still equals $expectedVersion.
     *
     * @param array<string, mixed> $content
     * @param array<string, mixed> $design
     * @return int|null the new version, or null on a version conflict
     */
    public static function saveContent(int $id, int $userId, int $expectedVersion, array $content, array $design, string $title, string $country, string $language, int $templateId, string $status): ?int
    {
        $stmt = Database::query(
            'UPDATE cvs SET content_json = ?, design_settings_json = ?, title = ?, country_code = ?, language_code = ?,
                template_id = ?, status = ?, version = version + 1
             WHERE id = ? AND user_id = ? AND version = ?',
            [self::encode($content), self::encode($design), $title, $country, $language, $templateId, $status, $id, $userId, $expectedVersion]
        );
        return $stmt->rowCount() === 1 ? $expectedVersion + 1 : null;
    }

    public static function rename(int $id, int $userId, string $title): bool
    {
        return Database::query('UPDATE cvs SET title = ?, version = version + 1 WHERE id = ? AND user_id = ?', [$title, $id, $userId])->rowCount() === 1;
    }

    public static function setPhoto(int $id, int $userId, ?string $photo): bool
    {
        return Database::query('UPDATE cvs SET photo_path = ? WHERE id = ? AND user_id = ?', [$photo, $id, $userId])->rowCount() === 1;
    }

    public static function delete(int $id, int $userId): bool
    {
        return Database::query('DELETE FROM cvs WHERE id = ? AND user_id = ?', [$id, $userId])->rowCount() === 1;
    }

    /** @return list<string> photo files owned by the user's CVs (for account deletion) */
    public static function photosOfUser(int $userId): array
    {
        return array_values(array_filter(array_column(
            Database::all('SELECT photo_path FROM cvs WHERE user_id = ? AND photo_path IS NOT NULL', [$userId]),
            'photo_path'
        )));
    }

    public static function photoInUse(string $photo): bool
    {
        return (bool) Database::value('SELECT 1 FROM cvs WHERE photo_path = ? LIMIT 1', [$photo]);
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM cvs');
    }

    /** @return list<array{country_code: string, n: int}> */
    public static function countByCountry(): array
    {
        return Database::all('SELECT country_code, COUNT(*) AS n FROM cvs GROUP BY country_code ORDER BY n DESC');
    }

    /** @return list<array{day: string, n: int}> */
    public static function createdByDay(int $days = 14): array
    {
        return Database::all(
            'SELECT DATE(created_at) AS day, COUNT(*) AS n FROM cvs
             WHERE created_at >= UTC_TIMESTAMP() - INTERVAL ? DAY GROUP BY DATE(created_at) ORDER BY day',
            [$days]
        );
    }

    public static function countUpdatedSince(int $hours): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM cvs WHERE updated_at >= UTC_TIMESTAMP() - INTERVAL ? HOUR', [$hours]);
    }

    /** @param array<string, mixed> $data */
    public static function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed>|null $row @return array<string, mixed>|null */
    private static function hydrate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['template_id'] = (int) $row['template_id'];
        $row['version'] = (int) $row['version'];
        $row['content'] = json_decode((string) $row['content_json'], true) ?: [];
        $row['design'] = json_decode((string) $row['design_settings_json'], true) ?: [];
        unset($row['content_json'], $row['design_settings_json']);
        return $row;
    }
}
