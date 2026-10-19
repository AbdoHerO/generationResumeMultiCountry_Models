<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database;
use App\Models\Setting;
use App\Models\Template;
use App\Services\Ai\GroqClient;
use App\Services\Cv\Design;
use App\Services\Cv\PdfService;
use App\Services\Cv\SampleContent;

/**
 * Global profile, PDF import (against the local Groq mock), filling CVs from the profile, and the
 * administrator's AI token.
 */
final class ProfileImportTest extends HttpTestCase
{
    private const ADMIN_PASSWORD = 'Admin-Test-Pass-42';
    private const TOKEN = 'gsk_test_token_0123456789abcdef';

    private function admin(): Client
    {
        $email = (string) config('app.admin.email');
        Database::query('UPDATE users SET password_hash = ?, must_change_password = 0, status = ? WHERE email = ?', [password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT), 'active', $email]);
        $c = $this->client();
        $c->get('/login');
        $c->post('/login', ['email' => $email, 'password' => self::ADMIN_PASSWORD]);
        $c->get('/admin/settings');
        return $c;
    }

    private function setToken(Client $admin, string $token, bool $enabled = true): array
    {
        return $admin->post('/admin/settings/ai', ['ai_api_key' => $token, 'ai_model' => 'qwen/qwen3.8-27b', 'ai_reasoning_effort' => 'none'] + ($enabled ? ['ai_import_enabled' => '1'] : []));
    }

    private function samplePdf(): string
    {
        $t = Template::findBySlug('fr-classic');
        $pdf = (new PdfService())->generate(['content' => SampleContent::for('FR', 'fr'), 'design' => Design::defaults($t, 'FR'), 'language_code' => 'fr', 'country_code' => 'FR'], $t);
        $file = tempnam(sys_get_temp_dir(), 'cv') . '.pdf';
        file_put_contents($file, $pdf['bytes']);
        return $file;
    }

    /** Runs the continue steps until the import is ready. @return array<string, mixed> last answer */
    private function finish(Client $c, int $id): array
    {
        for ($i = 0; $i < 20; $i++) {
            $r = $c->api("/api/profile/imports/$id/continue");
            if ($r['status'] !== 200 || !empty($r['data']['ready'])) {
                return $r;
            }
        }
        return $r;
    }

    protected function tearDown(): void
    {
        Database::query('DELETE FROM settings WHERE name LIKE ?', ['ai\_%']);
        Setting::flush();
        parent::tearDown();
    }

    public function testAdministratorManagesTheTokenWhichIsStoredEncrypted(): void
    {
        $admin = $this->admin();
        $r = $this->setToken($admin, self::TOKEN);
        $this->assertSame(302, $r['status']);
        $stored = (string) Database::value('SELECT value FROM settings WHERE name = ?', [GroqClient::SETTING_KEY]);
        $this->assertNotSame('', $stored);
        $this->assertStringNotContainsString(self::TOKEN, $stored, 'Encrypted at rest');
        $page = $admin->get('/admin/settings')['body'];
        $this->assertStringNotContainsString(self::TOKEN, $page, 'Never shown again');
        $this->assertStringContainsString('gsk_…cdef', $page, 'Masked');
        $audit = (string) Database::value("SELECT details FROM audit_logs WHERE action = 'admin.ai_settings_updated' ORDER BY id DESC LIMIT 1");
        $this->assertStringNotContainsString(self::TOKEN, $audit);
        $this->assertStringContainsString('replaced', $audit);
        // The connection test goes through the mock.
        $admin->post('/admin/settings/ai/test', []);
        $this->assertStringContainsString('Connected', $admin->get('/admin/settings')['body']);
        // Users cannot reach these routes.
        [$user] = $this->newUser();
        $this->assertSame(403, $user->post('/admin/settings/ai', ['ai_api_key' => 'x'])['status']);
    }

    public function testImportFromPdfReviewAndApplyToProfile(): void
    {
        $this->setToken($this->admin(), self::TOKEN);
        [$c] = $this->newUser();
        $c->get('/profile/import');
        $pdf = $this->samplePdf();
        $r = $c->upload('/api/profile/import', 'pdf', $pdf, 'application/pdf');
        @unlink($pdf);
        $this->assertSame(200, $r['status'], json_encode($r));
        $id = (int) $r['data']['id'];
        $done = $this->finish($c, $id);
        $this->assertTrue($done['data']['ready'] ?? false, json_encode($done));
        $this->assertSame(['extract', 'fr', 'en', 'zh'], array_keys($done['data']['progress']['steps']));

        // Review: three versions, the original one untouched, the others translated.
        $page = $c->get("/profile/imports/$id");
        $this->assertSame(200, $page['status']);
        preg_match('#id="editor-state">(.*?)</script>#s', $page['body'], $m);
        $state = json_decode($m[1], true);
        $data = $state['profile']['data'];
        $this->assertSame('Camille Laurent', $data['fr']['personal']['fullName']);
        $this->assertSame('Développeuse Full-Stack', $data['fr']['personal']['headline']);
        $this->assertSame('[en] Développeuse Full-Stack', $data['en']['personal']['headline']);
        $this->assertSame('[zh] Développeuse Full-Stack', $data['zh']['personal']['headline']);
        $this->assertSame('Camille Laurent', $data['zh']['personal']['fullName'], 'Names are kept');
        $this->assertSame('camille.laurent@example.fr', $data['en']['personal']['email'], 'E-mails are kept');

        // Edits before saving, with conflict detection.
        $data['en']['personal']['headline'] = 'Full-Stack Developer';
        $this->assertSame(200, $c->api("/api/profile/imports/$id/save", ['version' => $state['cv']['version'], 'data' => $data])['status']);
        $this->assertSame(409, $c->api("/api/profile/imports/$id/save", ['version' => $state['cv']['version'], 'data' => $data])['status']);
        $this->assertSame(200, $c->api("/api/profile/imports/$id/apply")['status']);

        $profile = $c->apiGet('/api/profile');
        $this->assertSame(['fr' => true, 'en' => true, 'zh' => true], $profile['data']['filled']);
        $this->assertSame('Full-Stack Developer', $profile['data']['data']['en']['personal']['headline']);
        $this->assertSame(409, $c->api("/api/profile/imports/$id/apply")['status'], 'An import is applied once');

        // Another user cannot see or use this import.
        [$other] = $this->newUser();
        $this->assertSame(404, $other->get("/profile/imports/$id")['status']);
        $this->assertSame(404, $other->api("/api/profile/imports/$id/continue")['status']);
        $this->assertSame(404, $other->api("/api/profile/imports/$id/apply")['status']);
    }

    public function testATokenChangeAppliesWithoutRestart(): void
    {
        $admin = $this->admin();
        $this->setToken($admin, 'gsk_wrong_token_0000000000000000');
        [$c] = $this->newUser();
        $pdf = $this->samplePdf();
        $id = (int) $c->upload('/api/profile/import', 'pdf', $pdf, 'application/pdf')['data']['id'];
        $r = $c->api("/api/profile/imports/$id/continue");
        $this->assertSame(502, $r['status']);
        $this->assertStringContainsString('token was refused', $r['message']);
        // The administrator fixes the token; the same running server uses it at once.
        $this->setToken($admin, self::TOKEN);
        $this->assertTrue($this->finish($c, $id)['data']['ready'] ?? false);
        @unlink($pdf);
    }

    public function testImportIsRefusedWhenDisabledOrNotAPdf(): void
    {
        $admin = $this->admin();
        $this->setToken($admin, self::TOKEN, false);
        [$c] = $this->newUser();
        $c->get('/profile/import');
        $fake = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($fake, '<?php echo 1;');
        $this->assertSame(503, $c->upload('/api/profile/import', 'pdf', $fake, 'application/pdf')['status'], 'Disabled by the administrator');
        $this->setToken($admin, self::TOKEN);
        $r = $c->upload('/api/profile/import', 'pdf', $fake, 'application/pdf');
        $this->assertSame(422, $r['status'], 'Not a PDF');
        @unlink($fake);
    }

    public function testProfileFillsNewCvsAndCanBeEdited(): void
    {
        [$c] = $this->newUser();
        $page = $c->get('/profile');
        $this->assertSame(200, $page['status']);
        preg_match('#id="editor-state">(.*?)</script>#s', $page['body'], $m);
        $state = json_decode($m[1], true);
        $data = $state['profile']['data'];
        $data['en'] = SampleContent::for('US', 'en');
        $data['zh'] = SampleContent::for('CN', 'zh');
        $saved = $c->api('/api/profile/save', ['version' => $state['cv']['version'], 'data' => $data]);
        $this->assertSame(200, $saved['status']);
        $this->assertSame(409, $c->api('/api/profile/save', ['version' => $state['cv']['version'], 'data' => $data])['status'], 'Stale saves are refused');

        // A new CV starts from the profile in its language, sections in the template's order.
        $c->get('/cvs/new');
        $this->assertStringContainsString('Fill this CV with my profile', $c->get('/cvs/new')['body']);
        $id = $this->createCvFromProfile($c, 'US', 'en', 'us-impact');
        $cv = $this->editorState($c, $id)['cv'];
        $this->assertSame('Jordan Mitchell', $cv['content']['personal']['fullName']);
        $order = Template::findBySlug('us-impact')['config']['section_order'] ?? ['summary', 'experience', 'skills', 'education', 'projects', 'certifications'];
        $types = array_column($cv['content']['sections'], 'type');
        $this->assertSame(array_values(array_intersect($order, $types)), array_slice($types, 0, count(array_intersect($order, $types))));

        // Without the option, the CV starts empty.
        $c->get('/cvs/new');
        $r = $c->post('/cvs', ['country' => 'US', 'language' => 'en', 'template' => 'us-impact']);
        preg_match('#/cvs/(\d+)/edit$#', $r['location'], $mm);
        $this->assertSame('', $this->editorState($c, (int) $mm[1])['cv']['content']['personal']['fullName']);

        // In the editor: fill an existing CV in Chinese; a French version that is empty is refused.
        $fill = $c->api("/api/cvs/$id/from-profile", ['language' => 'zh', 'country' => 'US', 'templateId' => $cv['templateId']]);
        $this->assertSame(200, $fill['status']);
        $this->assertSame('李明', $fill['data']['content']['personal']['fullName']);
        $this->assertSame(422, $c->api("/api/cvs/$id/from-profile", ['language' => 'fr', 'country' => 'US', 'templateId' => $cv['templateId']])['status']);
        $this->assertSame(422, $c->api("/api/cvs/$id/from-profile", ['language' => 'ar', 'country' => 'US', 'templateId' => $cv['templateId']])['status']);
        [$other] = $this->newUser();
        $this->assertSame(404, $other->api("/api/cvs/$id/from-profile", ['language' => 'en'])['status']);
    }

    private function createCvFromProfile(Client $c, string $country, string $language, string $template): int
    {
        $c->get('/cvs/new');
        $r = $c->post('/cvs', ['country' => $country, 'language' => $language, 'template' => $template, 'from_profile' => '1']);
        $this->assertSame(302, $r['status']);
        preg_match('#/cvs/(\d+)/edit$#', $r['location'], $m);
        return (int) $m[1];
    }
}
