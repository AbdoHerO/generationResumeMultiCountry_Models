<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Audit trail for administrative and security-relevant actions. Details never contain
 * passwords, tokens or CV content.
 */
final class AuditLog
{
    /** @param array<string, mixed> $details */
    public static function record(?int $actorId, string $action, ?string $targetType = null, ?int $targetId = null, array $details = [], ?string $ip = null): void
    {
        try {
            Database::query(
                'INSERT INTO audit_logs (actor_id, action, target_type, target_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)',
                [$actorId, $action, $targetType, $targetId, $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, $ip]
            );
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Audit log write failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /** @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public static function paginate(int $page, string $action = '', int $perPage = 30): array
    {
        $where = $action !== '' ? 'WHERE a.action LIKE :action' : '';
        $params = $action !== '' ? ['action' => str_replace(['%', '_'], ['\\%', '\\_'], $action) . '%'] : [];
        $total = (int) Database::value("SELECT COUNT(*) FROM audit_logs a $where", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $params['limit'] = $perPage;
        $params['offset'] = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT a.id, a.action, a.target_type, a.target_id, a.details, a.ip, a.created_at, u.email AS actor_email
             FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id $where
             ORDER BY a.id DESC LIMIT :limit OFFSET :offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $limit = 8): array
    {
        return self::paginate(1, '', $limit)['rows'];
    }
}
