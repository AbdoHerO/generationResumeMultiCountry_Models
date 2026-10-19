<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

/**
 * Default application settings, inserted only when missing.
 */
final class SettingsSeeder
{
    public static function run(): void
    {
        $defaults = [
            'app_name' => (string) config('app.name', 'ResumeAtlas'),
            'registration_open' => true,
            'support_email' => '',
            'max_cvs_per_user' => (int) config('app.limits.cvs_per_user', 50),
            'countries' => (object) [],
        ];
        foreach ($defaults as $name => $value) {
            Database::query(
                'INSERT IGNORE INTO settings (name, value) VALUES (?, ?)',
                [$name, json_encode($value, JSON_UNESCAPED_UNICODE)]
            );
        }
    }
}
