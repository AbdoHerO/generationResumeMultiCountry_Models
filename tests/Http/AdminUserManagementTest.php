<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database;

/** Administrators create, edit, reset and delete user accounts. */
final class AdminUserManagementTest extends HttpTestCase
{
    private const ADMIN_PASSWORD = 'Admin-Test-Pass-2026';

    private function admin(): Client
    {
        $email = (string) config('app.admin.email');
        Database::query('UPDATE users SET password_hash = ?, must_change_password = 0, status = ? WHERE email = ?', [password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT), 'active', $email]);
        $c = $this->client();
        $c->get('/login');
        $r = $c->post('/login', ['email' => $email, 'password' => self::ADMIN_PASSWORD]);
        $this->assertStringEndsWith('/admin', $r['location']);
        return $c;
    }

    private function signIn(string $email, string $password): array
    {
        $c = $this->client();
        $c->get('/login');
        return [$c, $c->post('/login', ['email' => $email, 'password' => $password])];
    }

    private function tempPassword(string $html): string
    {
        $this->assertMatchesRegularExpression('#<code id="temp-pw"[^>]*>([^<]+)</code>#', $html, 'The temporary password is shown');
        preg_match('#<code id="temp-pw"[^>]*>([^<]+)</code>#', $html, $m);
        return html_entity_decode($m[1]);
    }

    public function testAdministratorCreatesAnAccountWithATemporaryPassword(): void
    {
        $admin = $this->admin();
        $this->assertSame(200, $admin->get('/admin/users/new')['status']);
        $email = 'new' . bin2hex(random_bytes(4)) . '@example.test';
        $r = $admin->post('/admin/users', ['name' => 'Nadia Benali', 'email' => strtoupper($email), 'role' => 'user']);
        $this->assertSame(302, $r['status']);
        $this->assertMatchesRegularExpression('#/admin/users/\d+$#', $r['location']);
        $path = (string) parse_url($r['location'], PHP_URL_PATH);
        $page = $admin->get($path)['body'];
        $temp = $this->tempPassword($page);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/', $temp);
        $this->assertStringNotContainsString($temp, $admin->get($path)['body'], 'Shown only once');

        $row = Database::one('SELECT email, role, must_change_password, password_hash FROM users WHERE email = ?', [$email]);
        $this->assertSame($email, $row['email'], 'E-mail stored in lower case');
        $this->assertSame('user', $row['role']);
        $this->assertSame(1, (int) $row['must_change_password']);
        $this->assertStringStartsWith('$2y$', $row['password_hash']);
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM audit_logs WHERE details LIKE ?', ['%' . $temp . '%']), 'Not in the audit log');

        // The new user must choose a password before anything else.
        [$user, $r] = $this->signIn($email, $temp);
        $this->assertStringContainsString('/account/change-password', $r['location']);
        $this->assertStringContainsString('/account/change-password', $user->get('/dashboard')['location']);
        $user->get('/account/change-password');
        $user->post('/account/change-password', ['current_password' => $temp, 'password' => 'Nadia-Own-Pass-1', 'password_confirmation' => 'Nadia-Own-Pass-1']);
        $this->assertSame(200, $user->get('/dashboard')['status']);

        // The same e-mail cannot be used twice.
        $admin->get('/admin/users/new');
        $r = $admin->post('/admin/users', ['name' => 'Dup', 'email' => $email, 'role' => 'user']);
        $this->assertStringEndsWith('/admin/users/new', $r['location']);
        $this->assertStringContainsString('already uses this e-mail', $admin->get('/admin/users/new')['body']);
        // Invalid input is refused.
        $r = $admin->post('/admin/users', ['name' => '', 'email' => 'not-an-email', 'role' => 'user']);
        $this->assertStringEndsWith('/admin/users/new', $r['location']);
    }

    public function testAdministratorEditsResetsAndDeletesAnAccount(): void
    {
        $admin = $this->admin();
        [$user, $email] = $this->newUser('Old Name');
        $id = (int) Database::value('SELECT id FROM users WHERE email = ?', [$email]);
        $cvId = $this->createCv($user, 'FR', 'fr');

        // Edit name and e-mail.
        $this->assertSame(200, $admin->get("/admin/users/$id")['status']);
        $newEmail = 'renamed' . bin2hex(random_bytes(4)) . '@example.test';
        $r = $admin->post("/admin/users/$id", ['name' => 'New Name', 'email' => $newEmail]);
        $this->assertStringEndsWith("/admin/users/$id", $r['location']);
        $this->assertSame(['New Name', $newEmail], array_values(Database::one('SELECT name, email FROM users WHERE id = ?', [$id])));
        // An e-mail already used by another account is refused.
        $r = $admin->post("/admin/users/$id", ['name' => 'New Name', 'email' => (string) config('app.admin.email')]);
        $this->assertSame($newEmail, Database::value('SELECT email FROM users WHERE id = ?', [$id]));

        // Reset the password: the user is signed out and the old password stops working.
        $admin->get("/admin/users/$id");
        $r = $admin->post("/admin/users/$id/password");
        $this->assertStringEndsWith("/admin/users/$id", $r['location']);
        $temp = $this->tempPassword($admin->get("/admin/users/$id")['body']);
        $this->assertSame(302, $user->get('/dashboard')['status'], 'Signed out after the reset');
        [, $r] = $this->signIn($newEmail, 'Correct-Horse-42');
        $this->assertStringEndsWith('/login', $r['location'], 'Old password refused');
        [, $r] = $this->signIn($newEmail, $temp);
        $this->assertStringContainsString('/account/change-password', $r['location']);

        // Status and role from the user page come back to it.
        $admin->get("/admin/users/$id");
        $r = $admin->post("/admin/users/$id/status", ['status' => 'inactive', 'return' => 'user']);
        $this->assertStringEndsWith("/admin/users/$id", $r['location']);
        $this->assertSame('inactive', Database::value('SELECT status FROM users WHERE id = ?', [$id]));

        // Delete: the e-mail must be typed exactly.
        $admin->get("/admin/users/$id");
        $admin->post("/admin/users/$id/delete", ['confirm_email' => 'wrong@example.test']);
        $this->assertNotNull(Database::value('SELECT id FROM users WHERE id = ?', [$id]));
        $r = $admin->post("/admin/users/$id/delete", ['confirm_email' => strtoupper($newEmail)]);
        $this->assertStringEndsWith('/admin/users', $r['location']);
        $this->assertNull(Database::value('SELECT id FROM users WHERE id = ?', [$id]));
        $this->assertNull(Database::value('SELECT id FROM cvs WHERE id = ?', [$cvId]), 'Their CVs are deleted too');
        $this->assertSame(404, $admin->get("/admin/users/$id")['status']);

        foreach (['admin.user_updated', 'admin.user_password_reset', 'admin.user_deleted'] as $action) {
            $this->assertGreaterThan(0, (int) Database::value('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND target_id = ?', [$action, $id]), $action);
        }
    }

    public function testAdministratorCannotResetOrDeleteThemselves(): void
    {
        $admin = $this->admin();
        $id = (int) Database::value('SELECT id FROM users WHERE email = ?', [(string) config('app.admin.email')]);
        $admin->get("/admin/users/$id");
        $admin->post("/admin/users/$id/password");
        $this->assertSame(0, (int) Database::value('SELECT must_change_password FROM users WHERE id = ?', [$id]));
        $admin->post("/admin/users/$id/delete", ['confirm_email' => (string) config('app.admin.email')]);
        $this->assertNotNull(Database::value('SELECT id FROM users WHERE id = ?', [$id]));
        $this->assertSame(200, $admin->get('/admin')['status'], 'Still signed in');
    }

    public function testTemplatePreviewsCanBeEnlarged(): void
    {
        $page = $this->admin()->get('/admin/templates')['body'];
        $this->assertStringContainsString('id="tpl-dialog"', $page);
        $this->assertSame(25, substr_count($page, 'class="thumb-zoom-btn"'), 'Every thumbnail opens the large preview');
        $this->assertGreaterThanOrEqual(50, substr_count($page, 'data-preview="'), 'Thumbnail and Preview button for each template');
    }

    public function testUsersCannotManageAccounts(): void
    {
        [$user] = $this->newUser();
        [, $victimEmail] = $this->newUser();
        $victim = (int) Database::value('SELECT id FROM users WHERE email = ?', [$victimEmail]);
        $this->assertSame(403, $user->get('/admin/users/new')['status']);
        $this->assertSame(403, $user->get("/admin/users/$victim")['status']);
        $this->assertSame(403, $user->post('/admin/users', ['name' => 'X', 'email' => 'x' . bin2hex(random_bytes(3)) . '@example.test', 'role' => 'admin'])['status']);
        $this->assertSame(403, $user->post("/admin/users/$victim/password")['status']);
        $this->assertSame(403, $user->post("/admin/users/$victim/delete", ['confirm_email' => $victimEmail])['status']);
        $this->assertNotNull(Database::value('SELECT id FROM users WHERE id = ?', [$victim]));
    }
}
