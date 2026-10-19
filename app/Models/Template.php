<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Template
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return self::hydrate(Database::one('SELECT * FROM templates WHERE id = ?', [$id]));
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        return self::hydrate(Database::one('SELECT * FROM templates WHERE slug = ?', [$slug]));
    }

    /** @return list<array<string, mixed>> */
    public static function all(bool $activeOnly = true, ?string $country = null): array
    {
        $sql = 'SELECT * FROM templates WHERE 1 = 1';
        $params = [];
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        if ($country !== null) {
            $sql .= ' AND country_code = ?';
            $params[] = $country;
        }
        $sql .= ' ORDER BY country_code = ? DESC, sort_order, id';
        $params[] = $country ?? 'FR';
        $rows = Database::all($sql, $params);
        return array_values(array_map(fn (array $r): array => (array) self::hydrate($r), $rows));
    }

    /** First active template of a country (the simple, clean one by default). @return array<string, mixed>|null */
    public static function defaultFor(string $country): ?array
    {
        return self::hydrate(Database::one(
            'SELECT * FROM templates WHERE country_code = ? AND is_active = 1 ORDER BY sort_order, id LIMIT 1',
            [$country]
        ));
    }

    public static function count(bool $activeOnly = false): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM templates' . ($activeOnly ? ' WHERE is_active = 1' : ''));
    }

    /** @param array<string, mixed> $fields */
    public static function updateMeta(int $id, array $fields): void
    {
        Database::query(
            'UPDATE templates SET name = ?, description = ?, category = ?, supported_languages = ?,
                is_recommended = ?, sort_order = ? WHERE id = ?',
            [
                $fields['name'], $fields['description'], $fields['category'], $fields['supported_languages'],
                $fields['is_recommended'] ? 1 : 0, (int) $fields['sort_order'], $id,
            ]
        );
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::query('UPDATE templates SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    /** @return list<array<string, mixed>> */
    public static function usage(): array
    {
        return Database::all(
            'SELECT t.id, t.slug, t.name, t.country_code, t.is_active, COUNT(c.id) AS cv_count
             FROM templates t LEFT JOIN cvs c ON c.template_id = t.id
             GROUP BY t.id, t.slug, t.name, t.country_code, t.is_active ORDER BY cv_count DESC, t.sort_order LIMIT 10'
        );
    }

    /** @param array<string, mixed>|null $row @return array<string, mixed>|null */
    private static function hydrate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['is_active'] = (bool) $row['is_active'];
        $row['is_recommended'] = (bool) $row['is_recommended'];
        $row['config'] = json_decode((string) $row['configuration_json'], true) ?: [];
        $row['languages'] = array_values(array_filter(explode(',', (string) $row['supported_languages'])));
        unset($row['configuration_json']);
        return $row;
    }
}
