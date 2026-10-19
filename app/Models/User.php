<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Users. Methods return arrays without the password hash unless explicitly asked for.
 */
final class User
{
    private const PUBLIC_COLUMNS = 'id, name, email, role, status, must_change_password, created_at, updated_at, last_login_at';

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT ' . self::PUBLIC_COLUMNS . ' FROM users WHERE id = ?', [$id]);
    }

    /** Includes password_hash; for authentication only. @return array<string, mixed>|null */
    public static function findForAuth(string $email): ?array
    {
        return Database::one('SELECT ' . self::PUBLIC_COLUMNS . ', password_hash FROM users WHERE email = ?', [self::normalizeEmail($email)]);
    }

    public static function passwordHash(int $id): ?string
    {
        $hash = Database::value('SELECT password_hash FROM users WHERE id = ?', [$id]);
        return is_string($hash) ? $hash : null;
    }

    public static function emailExists(string $email): bool
    {
        return (bool) Database::value('SELECT 1 FROM users WHERE email = ?', [self::normalizeEmail($email)]);
    }

    public static function create(string $name, string $email, string $password, string $role = 'user'): int
    {
        Database::query(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)',
            [$name, self::normalizeEmail($email), password_hash($password, PASSWORD_DEFAULT), $role === 'admin' ? 'admin' : 'user']
        );
        return Database::lastId();
    }

    public static function setPassword(int $id, string $password): void
    {
        Database::query(
            'UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]
        );
    }

    public static function rehashIfNeeded(int $id, string $password, string $hash): void
    {
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
        }
    }

    public static function touchLogin(int $id): void
    {
        Database::query('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    public static function updateProfile(int $id, string $name): void
    {
        Database::query('UPDATE users SET name = ? WHERE id = ?', [$name, $id]);
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::query('UPDATE users SET status = ? WHERE id = ?', [$status === 'inactive' ? 'inactive' : 'active', $id]);
    }

    public static function setRole(int $id, string $role): void
    {
        Database::query('UPDATE users SET role = ? WHERE id = ?', [$role === 'admin' ? 'admin' : 'user', $id]);
    }

    public static function delete(int $id): void
    {
        Database::query('DELETE FROM users WHERE id = ?', [$id]);
    }

    public static function count(?string $role = null): int
    {
        return $role === null
            ? (int) Database::value('SELECT COUNT(*) FROM users')
            : (int) Database::value('SELECT COUNT(*) FROM users WHERE role = ?', [$role]);
    }

    public static function countActiveAdmins(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
    }

    /**
     * Paginated list for the admin, with each user's CV count (metadata only).
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public static function paginate(string $search, string $status, int $page, int $perPage = 20): array
    {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Database::value("SELECT COUNT(*) FROM users u $sqlWhere", $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $params['limit'] = $perPage;
        $params['offset'] = ($page - 1) * $perPage;
        $rows = Database::all(
            "SELECT u.id, u.name, u.email, u.role, u.status, u.must_change_password, u.created_at, u.last_login_at,
                    (SELECT COUNT(*) FROM cvs c WHERE c.user_id = u.id) AS cv_count
             FROM users u $sqlWhere ORDER BY u.created_at DESC, u.id DESC LIMIT :limit OFFSET :offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<array{day: string, n: int}> */
    public static function signupsByDay(int $days = 14): array
    {
        return Database::all(
            'SELECT DATE(created_at) AS day, COUNT(*) AS n FROM users
             WHERE created_at >= UTC_TIMESTAMP() - INTERVAL ? DAY GROUP BY DATE(created_at) ORDER BY day',
            [$days]
        );
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
