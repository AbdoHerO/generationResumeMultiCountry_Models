<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database;

/** End-to-end flows through the real HTTP stack (router, middleware, sessions, views, API). */
final class HttpFlowTest extends HttpTestCase
{
    public function testPublicPagesAndSecurityHeaders(): void
    {
        $c = $this->client();
        foreach (['/', '/templates', '/privacy', '/login', '/register', '/forgot-password', '/templates/fr-classic/preview'] as $path) {
            $r = $c->get($path);
            $this->assertSame(200, $r['status'], $path);
        }
        $r = $c->get('/');
        $this->assertStringContainsString("default-src 'self'", $r['headers']['content-security-policy'] ?? '');
        $this->assertStringContainsString("frame-ancestors 'self'", $r['headers']['content-security-policy'] ?? '');
        $this->assertSame('nosniff', $r['headers']['x-content-type-options'] ?? '');
        $this->assertSame('SAMEORIGIN', $r['headers']['x-frame-options'] ?? '');
        $cookie = $this->client()->get('/login')['headers']['set-cookie'] ?? '';
        $this->assertMatchesRegularExpression('/HttpOnly/i', $cookie);
        $this->assertMatchesRegularExpression('/SameSite=Lax/i', $cookie);
        $this->assertSame(404, $c->get('/does-not-exist')['status']);
        $health = json_decode($c->get('/health')['body'], true);
        $this->assertSame('ok', $health['status'] ?? null);
    }

    public function testAdminCredentialsAreNeverShownPublicly(): void
    {
        $c = $this->client();
        foreach (['/', '/login', '/register', '/templates', '/privacy'] as $path) {
            $this->assertStringNotContainsString('123456789', $c->get($path)['body'], $path);
        }
    }

    public function testGuestsAreSentToSignIn(): void
    {
        $c = $this->client();
        foreach (['/dashboard', '/cvs/new', '/account', '/admin'] as $path) {
            $r = $c->get($path);
            $this->assertSame(302, $r['status'], $path);
            $this->assertStringContainsString('/login', $r['location']);
        }
        $this->assertSame(401, $c->apiGet('/api/cvs/1')['status']);
    }

    public function testFormsRequireCsrfToken(): void
    {
        $c = $this->client();
        $c->get('/login');
        // HTML forms go back with an explanation; JSON calls get 419.
        foreach ([[], ['_token' => str_repeat('0', 64)]] as $token) {
            $r = $c->post('/login', ['email' => 'a@b.co', 'password' => 'x'] + $token, false);
            $this->assertSame(302, $r['status']);
            $this->assertStringContainsString('session expired', $c->get('/login')['body']);
        }
        [$user] = $this->newUser();
        $id = $this->createCv($user);
        $user->csrf = 'forged';
        $this->assertSame(419, $user->api("/api/cvs/$id/delete")['status']);
        $this->assertSame(200, $user->get("/cvs/$id/edit")['status'], 'The forged request deleted nothing');
    }

    public function testRegistrationValidation(): void
    {
        $c = $this->client();
        $c->get('/register');
        $r = $c->post('/register', ['name' => '', 'email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'other', 'terms' => '1']);
        $this->assertSame(302, $r['status']);
        $this->assertStringEndsWith('/register', $r['location']);
        [, $email] = $this->newUser();
        $c2 = $this->client();
        $c2->get('/register');
        $r = $c2->post('/register', ['name' => 'Dup', 'email' => strtoupper($email), 'password' => 'Correct-Horse-42', 'password_confirmation' => 'Correct-Horse-42', 'terms' => '1']);
        $this->assertStringEndsWith('/register', $r['location'], 'An existing e-mail (any case) is refused');
    }

    public function testLoginIsThrottled(): void
    {
        [, $email] = $this->newUser();
        $c = $this->client();
        $c->get('/login');
        for ($i = 0; $i < 6; $i++) {
            $c->post('/login', ['email' => $email, 'password' => 'wrong-password']);
        }
        // Even the right password is refused while throttled.
        $r = $c->post('/login', ['email' => $email, 'password' => 'Correct-Horse-42']);
        $this->assertStringEndsWith('/login', $r['location']);
        $this->assertStringContainsString('Too many failed attempts', $c->get('/login')['body']);
    }

    public function testEditorSaveConflictPreviewAndPdf(): void
    {
        [$c] = $this->newUser();
        $id = $this->createCv($c, 'FR', 'fr', 'fr-modern');
        $state = $this->editorState($c, $id);
        $this->assertSame('FR', $state['cv']['country']);
        $this->assertCount(8, array_filter($state['templates'], fn ($t) => $t['country'] === 'FR'));

        $payload = $this->savePayload($state);
        $payload['content']['personal']['fullName'] = 'Élise Durand';
        $payload['content']['personal']['email'] = 'elise@example.test';
        $payload['user_id'] = 1; // must be ignored
        $res = $c->api("/api/cvs/$id/save", $payload);
        $this->assertSame(200, $res['status'], json_encode($res));
        $this->assertSame($payload['version'] + 1, $res['data']['version']);

        // A second save with the old version is a conflict, and nothing is overwritten.
        $payload['content']['personal']['fullName'] = 'Stale Tab';
        $res = $c->api("/api/cvs/$id/save", $payload);
        $this->assertSame(409, $res['status']);
        $this->assertSame('version_conflict', $res['code']);
        $this->assertSame('Élise Durand', $this->editorState($c, $id)['cv']['content']['personal']['fullName']);

        // Invalid template for the country is refused.
        $bad = $this->savePayload($this->editorState($c, $id));
        $bad['country'] = 'US';
        $this->assertSame(422, $c->api("/api/cvs/$id/save", $bad)['status']);

        $preview = $c->api("/api/cvs/$id/preview", $this->savePayload($this->editorState($c, $id)));
        $this->assertSame(200, $preview['status']);
        $this->assertStringContainsString('Élise Durand', $preview['data']['html']);
        $pages = $c->api("/api/cvs/$id/pages", $this->savePayload($this->editorState($c, $id)));
        $this->assertSame(1, $pages['data']['pages']);

        $r = $c->get("/cvs/$id/download");
        $this->assertSame(200, $r['status']);
        $this->assertSame('application/pdf', $r['headers']['content-type']);
        $this->assertStringStartsWith('%PDF-', $r['body']);
        $this->assertStringContainsString('Élise_Durand_CV_France.pdf', rawurldecode($r['headers']['content-disposition']));
        $this->assertSame('1', $r['headers']['x-page-count']);
    }

    public function testLibraryRenameDuplicateDelete(): void
    {
        [$c] = $this->newUser();
        $id = $this->createCv($c, 'CN', 'zh');
        $this->assertSame(200, $c->api("/api/cvs/$id/rename", ['title' => '投递 · 字节跳动'])['status']);
        $this->assertStringContainsString('投递 · 字节跳动', $c->get('/dashboard')['body']);
        $this->assertStringContainsString('投递', $c->get('/dashboard?q=' . rawurlencode('字节'))['body']);
        $this->assertSame(422, $c->api("/api/cvs/$id/rename", ['title' => '   '])['status']);
        $dup = $c->api("/api/cvs/$id/duplicate");
        $this->assertSame(200, $dup['status']);
        $copyId = (int) $dup['data']['id'];
        $this->assertNotSame($id, $copyId);
        $this->assertSame(200, $c->api("/api/cvs/$copyId/delete")['status']);
        $this->assertSame(404, $c->get("/cvs/$copyId/edit")['status']);
    }

    public function testUsersCannotReachOtherUsersCvs(): void
    {
        [$alice] = $this->newUser('Alice');
        [$bob] = $this->newUser('Bob');
        $id = $this->createCv($alice, 'US', 'en');
        $state = $this->editorState($alice, $id);

        $this->assertSame(404, $bob->get("/cvs/$id/edit")['status']);
        $this->assertSame(404, $bob->get("/cvs/$id/download")['status']);
        $this->assertSame(404, $bob->get("/cvs/$id/render")['status']);
        $this->assertSame(404, $bob->get("/cvs/$id/photo")['status']);
        $this->assertSame(404, $bob->apiGet("/api/cvs/$id")['status']);
        $payload = $this->savePayload($state);
        $payload['content']['personal']['fullName'] = 'Hacked';
        foreach (['save', 'preview', 'pages', 'rename', 'duplicate', 'delete', 'photo/delete'] as $action) {
            $this->assertSame(404, $bob->api("/api/cvs/$id/$action", $payload + ['title' => 'x'])['status'], $action);
        }
        $this->assertNotSame('Hacked', $this->editorState($alice, $id)['cv']['content']['personal']['fullName']);
        $this->assertSame(403, $bob->get('/admin')['status'], 'Users cannot open administration');
        $this->assertSame(403, $bob->post('/admin/settings', ['app_name' => 'x'])['status']);
    }

    public function testPhotoUploadValidationAndPrivacy(): void
    {
        [$c] = $this->newUser();
        [$other] = $this->newUser();
        $id = $this->createCv($c, 'FR', 'fr', 'fr-classic');
        $c->get("/cvs/$id/edit");

        $png = tempnam(sys_get_temp_dir(), 'png');
        $img = imagecreatetruecolor(240, 320);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 120, 60));
        imagepng($img, $png);
        imagedestroy($img);
        $res = $c->upload("/api/cvs/$id/photo", 'photo', $png, 'image/png');
        $this->assertSame(200, $res['status'], json_encode($res));
        $photo = $c->get("/cvs/$id/photo");
        $this->assertSame(200, $photo['status']);
        $this->assertSame('image/jpeg', $photo['headers']['content-type']);
        $this->assertSame(404, $other->get("/cvs/$id/photo")['status'], 'Photos are private');

        $fake = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($fake, '<?php phpinfo();');
        $this->assertSame(422, $c->upload("/api/cvs/$id/photo", 'photo', $fake, 'image/png')['status']);
        @unlink($png);
        @unlink($fake);

        $this->assertSame(200, $c->api("/api/cvs/$id/photo/delete")['status']);
        $this->assertSame(404, $c->get("/cvs/$id/photo")['status']);
    }

    public function testAdministratorMustChangeTheInitialPassword(): void
    {
        $email = (string) config('app.admin.email');
        Database::query('UPDATE users SET password_hash = ?, must_change_password = 1, status = ? WHERE email = ?', [password_hash('123456789', PASSWORD_DEFAULT), 'active', $email]);

        $admin = $this->client();
        $admin->get('/login');
        $r = $admin->post('/login', ['email' => $email, 'password' => '123456789']);
        $this->assertStringContainsString('/account/change-password', $r['location']);
        $r = $admin->get('/admin');
        $this->assertSame(302, $r['status']);
        $this->assertStringContainsString('/account/change-password', $r['location'], 'Administration is blocked until the password is changed');

        $admin->get('/account/change-password');
        $r = $admin->post('/account/change-password', ['current_password' => '123456789', 'password' => '123456789', 'password_confirmation' => '123456789']);
        $this->assertStringContainsString('/account/change-password', $r['location'], 'The initial password cannot be kept');
        $admin->get('/account/change-password');
        $r = $admin->post('/account/change-password', ['current_password' => '123456789', 'password' => 'New-Admin-Pass-2026', 'password_confirmation' => 'New-Admin-Pass-2026']);
        $this->assertSame(302, $r['status']);
        $this->assertSame(200, $admin->get('/admin')['status']);
        $row = Database::one('SELECT password_hash, must_change_password FROM users WHERE email = ?', [$email]);
        $this->assertSame(0, (int) $row['must_change_password']);
        $this->assertStringStartsWith('$2y$', $row['password_hash'], 'Stored as a bcrypt hash');

        foreach (['/admin/users', '/admin/templates', '/admin/countries', '/admin/settings', '/admin/audit'] as $p) {
            $this->assertSame(200, $admin->get($p)['status'], $p);
        }

        // Deactivating a user ends their session and blocks sign-in.
        [$user, $userEmail] = $this->newUser();
        $uid = (int) Database::value('SELECT id FROM users WHERE email = ?', [$userEmail]);
        $admin->get('/admin/users');
        $r = $admin->post("/admin/users/$uid/status", ['status' => 'inactive']);
        $this->assertSame(302, $r['status']);
        $this->assertSame(302, $user->get('/dashboard')['status'], 'A deactivated user is signed out');
        $c = $this->client();
        $c->get('/login');
        $r = $c->post('/login', ['email' => $userEmail, 'password' => 'Correct-Horse-42']);
        $this->assertStringNotContainsString('/dashboard', $r['location']);

        // Admin cannot demote or deactivate themselves.
        $adminId = (int) Database::value('SELECT id FROM users WHERE email = ?', [$email]);
        $admin->get('/admin/users');
        $admin->post("/admin/users/$adminId/role", ['role' => 'user']);
        $this->assertSame('admin', Database::value('SELECT role FROM users WHERE id = ?', [$adminId]));

        $this->assertGreaterThan(0, (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'admin.%'"));
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM audit_logs WHERE details LIKE ?', ['%New-Admin-Pass%']), 'No password in the audit log');
    }
}
