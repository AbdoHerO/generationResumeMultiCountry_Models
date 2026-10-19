<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

/**
 * Creates the bootstrap administrator once. Idempotent: an existing account with the same
 * e-mail is never duplicated, and its password is never reset by re-running the seeder.
 * The password is stored only as a password_hash() digest and must be changed at first sign-in.
 */
final class AdminSeeder
{
    public static function run(): bool
    {
        $email = strtolower(trim((string) config('app.admin.email')));
        $password = (string) config('app.admin.initial_password');
        if ($email === '' || $password === '') {
            return false;
        }
        $existing = Database::one('SELECT id, role FROM users WHERE email = ?', [$email]);
        if ($existing) {
            return false;
        }
        Database::query(
            "INSERT INTO users (name, email, password_hash, role, status, must_change_password)
             VALUES (?, ?, ?, 'admin', 'active', 1)",
            [(string) config('app.admin.name', 'Administrator'), $email, password_hash($password, PASSWORD_DEFAULT)]
        );
        Database::query(
            "INSERT INTO audit_logs (actor_id, action, target_type, target_id, details) VALUES (NULL, 'admin.seeded', 'user', ?, NULL)",
            [\App\Core\Database::lastId()]
        );
        return true;
    }
}
