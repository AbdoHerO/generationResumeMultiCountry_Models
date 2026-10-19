<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Models\Template;
use App\Models\User;
use App\Services\Cv\CvSchema;
use App\Services\Cv\Design;
use App\Services\Cv\SampleContent;

/**
 * DEVELOPMENT ONLY (refused when APP_ENV=production): a demo account with one sample CV per
 * country, clearly separated from production data. Idempotent.
 */
final class DemoSeeder
{
    public const EMAIL = 'demo@example.test';

    public static function run(callable $out): void
    {
        $user = User::findForAuth(self::EMAIL);
        if (!$user) {
            $id = User::create('Demo User', self::EMAIL, 'demo-password-1');
            $out('Demo user created: ' . self::EMAIL . ' / demo-password-1');
        } else {
            $id = (int) $user['id'];
            $out('Demo user already exists.');
        }
        $pairs = ['FR' => 'fr', 'MA' => 'ar', 'US' => 'en', 'CA' => 'en', 'CN' => 'zh'];
        foreach ($pairs as $country => $lang) {
            $template = Template::defaultFor($country);
            $title = 'Demo · ' . $country;
            if (Database::value('SELECT 1 FROM cvs WHERE user_id = ? AND title = ?', [$id, $title])) {
                continue;
            }
            $content = SampleContent::for($country, $lang);
            Database::query(
                'INSERT INTO cvs (user_id, title, country_code, language_code, template_id, content_json, design_settings_json, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $title, $country, $lang, $template['id'], json_encode($content, JSON_UNESCAPED_UNICODE),
                    json_encode(Design::defaults($template, $country)), CvSchema::status($content)]
            );
            $out("Sample CV created: $title");
        }
    }
}
