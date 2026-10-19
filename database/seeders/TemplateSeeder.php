<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;

/**
 * Inserts missing templates and refreshes the code-owned `configuration_json` of existing ones.
 * Administrator-managed fields (name, description, category, availability, order, recommended,
 * languages) are only set on insert, so re-running the seeder never undoes an admin's changes.
 */
final class TemplateSeeder
{
    public static function run(): int
    {
        $definitions = require BASE_PATH . '/config/templates.php';
        $countries = require BASE_PATH . '/config/countries.php';
        $order = 0;
        foreach ($definitions as $def) {
            $order += 10;
            $config = $def['config'];
            $languages = $config['languages'] ?? $countries[$def['country']]['languages'];
            $meta = json_encode([
                'layout' => $config['layout'],
                'accent' => $config['accent'],
                'font' => $config['font'],
                'photo' => $config['photo'],
            ]);
            $existing = Database::one('SELECT id FROM templates WHERE slug = ?', [$def['slug']]);
            if ($existing) {
                Database::query(
                    'UPDATE templates SET configuration_json = ?, preview_meta = ? WHERE id = ?',
                    [json_encode($config, JSON_UNESCAPED_UNICODE), $meta, (int) $existing['id']]
                );
                continue;
            }
            Database::query(
                'INSERT INTO templates (slug, name, description, country_code, supported_languages, category,
                    is_recommended, preview_meta, configuration_json, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
                [
                    $def['slug'], $def['name'], $def['description'], $def['country'], implode(',', $languages),
                    $def['category'], $def['recommended'] ? 1 : 0, $meta, json_encode($config, JSON_UNESCAPED_UNICODE), $order,
                ]
            );
        }
        return count($definitions);
    }
}
