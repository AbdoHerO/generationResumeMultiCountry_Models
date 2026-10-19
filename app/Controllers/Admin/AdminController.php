<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Cv;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Services\Ai\GroqClient;
use App\Services\Auth;
use App\Services\Countries;
use App\Services\Secrets;

/**
 * Administration: statistics, country/language configuration, settings and the audit log.
 * Administrators see CV metadata only, never CV contents.
 */
final class AdminController extends Controller
{
    public function dashboard(Request $request): Response
    {
        return $this->view('admin/dashboard', [
            'title' => 'Administration',
            'stats' => [
                'users' => User::count(),
                'admins' => User::count('admin'),
                'cvs' => Cv::count(),
                'templates' => Template::count(),
                'activeTemplates' => Template::count(true),
                'cvsUpdated24h' => Cv::countUpdatedSince(24),
            ],
            'byCountry' => Cv::countByCountry(),
            'signups' => User::signupsByDay(14),
            'created' => Cv::createdByDay(14),
            'topTemplates' => Template::usage(),
            'recent' => AuditLog::recent(8),
        ], 'layouts/admin');
    }

    public function countries(Request $request): Response
    {
        return $this->view('admin/countries', ['title' => 'Countries & languages', 'countries' => Countries::all()], 'layouts/admin');
    }

    public function saveCountries(Request $request): Response
    {
        $base = config('countries', []);
        $input = $request->post['countries'] ?? [];
        $out = [];
        $enabledCount = 0;
        foreach ($base as $code => $c) {
            $row = is_array($input[$code] ?? null) ? $input[$code] : [];
            $langs = array_values(array_intersect($c['languages'], array_map('strval', (array) ($row['languages'] ?? []))));
            if ($langs === []) {
                $langs = [$c['default_language']];
            }
            $default = (string) ($row['default_language'] ?? $c['default_language']);
            if (!in_array($default, $langs, true)) {
                $default = $langs[0];
            }
            $enabled = !empty($row['enabled']);
            $enabledCount += $enabled ? 1 : 0;
            $out[$code] = ['enabled' => $enabled, 'languages' => $langs, 'default_language' => $default];
        }
        if ($enabledCount === 0) {
            Session::flash('error', 'At least one country must stay enabled.');
            return $this->redirect('/admin/countries');
        }
        Setting::set('countries', $out);
        AuditLog::record(Auth::id(), 'admin.countries_updated', 'settings', null, $out, $request->ip());
        Session::flash('success', 'Country settings saved.');
        return $this->redirect('/admin/countries');
    }

    public function settings(Request $request): Response
    {
        return $this->view('admin/settings', [
            'title' => 'Application settings',
            'settings' => [
                'app_name' => (string) Setting::get('app_name', config('app.name')),
                'registration_open' => (bool) Setting::get('registration_open', true),
                'support_email' => (string) Setting::get('support_email', ''),
                'max_cvs_per_user' => (int) Setting::get('max_cvs_per_user', 50),
            ],
            'ai' => self::aiView(),
        ], 'layouts/admin');
    }

    public function saveSettings(Request $request): Response
    {
        $name = trim($request->string('app_name'));
        $email = trim($request->string('support_email'));
        $max = $request->int('max_cvs_per_user', 50);
        $errors = [];
        if ($name === '' || mb_strlen($name) > 60) {
            $errors['app_name'] = 'Use 1 to 60 characters.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['support_email'] = 'Enter a valid e-mail address or leave it empty.';
        }
        if ($max < 1 || $max > 500) {
            $errors['max_cvs_per_user'] = 'Choose a number between 1 and 500.';
        }
        if ($errors) {
            return $this->backWithErrors('/admin/settings', $errors, $request->post);
        }
        Setting::set('app_name', $name);
        Setting::set('support_email', $email);
        Setting::set('registration_open', (bool) $request->input('registration_open'));
        Setting::set('max_cvs_per_user', $max);
        AuditLog::record(Auth::id(), 'admin.settings_updated', 'settings', null, [
            'app_name' => $name, 'registration_open' => (bool) $request->input('registration_open'), 'max_cvs_per_user' => $max,
        ], $request->ip());
        Session::flash('success', 'Settings saved.');
        return $this->redirect('/admin/settings');
    }

    /**
     * AI (Groq) settings. The token is stored encrypted in the settings table and read again
     * before every API call, so a new token is used at once, without any restart.
     */
    public function saveAi(Request $request): Response
    {
        $model = trim($request->string('ai_model'));
        $reasoning = $request->string('ai_reasoning_effort');
        $token = trim((string) $request->input('ai_api_key', ''));
        $errors = [];
        if ($model === '' || mb_strlen($model) > 100 || !preg_match('#^[A-Za-z0-9._/:-]+$#', $model)) {
            $errors['ai_model'] = 'Enter a model name such as qwen/qwen3.8-27b.';
        }
        if (!in_array($reasoning, GroqClient::REASONING, true)) {
            $errors['ai_reasoning_effort'] = 'Choose a valid option.';
        }
        if ($token !== '' && (strlen($token) < 20 || strlen($token) > 300 || preg_match('/\s/', $token))) {
            $errors['ai_api_key'] = 'This does not look like an API token.';
        }
        if ($errors) {
            return $this->backWithErrors('/admin/settings', $errors, ['ai_model' => $model]);
        }
        Setting::set(GroqClient::SETTING_MODEL, $model);
        Setting::set(GroqClient::SETTING_REASONING, $reasoning);
        Setting::set(GroqClient::SETTING_ENABLED, (bool) $request->input('ai_import_enabled'));
        $tokenChange = 'unchanged';
        if ($request->input('ai_remove_key')) {
            Database::query('DELETE FROM settings WHERE name = ?', [GroqClient::SETTING_KEY]);
            Setting::flush();
            $tokenChange = 'removed';
        } elseif ($token !== '') {
            Setting::set(GroqClient::SETTING_KEY, Secrets::encrypt($token));
            $tokenChange = 'replaced';
        }
        // The audit entry never contains the token.
        AuditLog::record(Auth::id(), 'admin.ai_settings_updated', 'settings', null, [
            'model' => $model, 'reasoning' => $reasoning, 'enabled' => (bool) $request->input('ai_import_enabled'), 'token' => $tokenChange,
        ], $request->ip());
        Session::flash('success', $tokenChange === 'replaced' ? 'AI settings saved. The new token is used from the next request.' : 'AI settings saved.');
        return $this->redirect('/admin/settings#ai');
    }

    public function testAi(Request $request): Response
    {
        $result = GroqClient::testConnection();
        AuditLog::record(Auth::id(), 'admin.ai_tested', 'settings', null, ['ok' => $result['ok'], 'model' => $result['model']], $request->ip());
        Session::flash($result['ok'] ? 'success' : 'error', $result['message'] . ' (' . $result['ms'] . ' ms)');
        return $this->redirect('/admin/settings#ai');
    }

    /** @return array<string, mixed> what the settings page shows about the AI service (never the token) */
    private static function aiView(): array
    {
        $s = GroqClient::settings();
        return [
            'url' => $s['url'], 'model' => $s['model'], 'reasoning' => $s['reasoning'], 'enabled' => $s['enabled'],
            'keySource' => $s['keySource'], 'keyMask' => $s['key'] !== null ? Secrets::mask($s['key']) : null,
        ];
    }

    public function audit(Request $request): Response
    {
        return $this->view('admin/audit', [
            'title' => 'Activity log',
            'log' => AuditLog::paginate($request->int('page', 1), preg_replace('/[^a-z_.]/', '', $request->string('action')) ?? ''),
            'action' => $request->string('action'),
        ], 'layouts/admin');
    }
}
