<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * Country configuration (config/countries.php) merged with the administrator's overrides
 * (enabled flag, default language, enabled languages) stored in the `countries` setting.
 */
final class Countries
{
    public const SUPPORTED_LANGUAGES = ['en', 'fr', 'ar', 'zh'];

    /** @return array<string, array<string, mixed>> */
    public static function all(bool $enabledOnly = false): array
    {
        $base = config('countries', []);
        $overrides = Setting::get('countries', []);
        $out = [];
        foreach ($base as $code => $country) {
            $o = is_array($overrides[$code] ?? null) ? $overrides[$code] : [];
            $country['code'] = $code;
            $country['enabled'] = (bool) ($o['enabled'] ?? true);
            $langs = array_values(array_intersect($country['languages'], (array) ($o['languages'] ?? $country['languages'])));
            $country['languages'] = $langs !== [] ? $langs : $country['languages'];
            $default = (string) ($o['default_language'] ?? $country['default_language']);
            $country['default_language'] = in_array($default, $country['languages'], true) ? $default : $country['languages'][0];
            $country['all_languages'] = $base[$code]['languages'];
            if ($enabledOnly && !$country['enabled']) {
                continue;
            }
            $out[$code] = $country;
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function get(string $code): ?array
    {
        return self::all()[strtoupper($code)] ?? null;
    }

    public static function exists(string $code, bool $enabledOnly = true): bool
    {
        return isset(self::all($enabledOnly)[strtoupper($code)]);
    }

    public static function name(string $code): string
    {
        return (string) (config('countries.' . strtoupper($code) . '.name') ?? $code);
    }

    public static function languageName(string $lang): string
    {
        return (string) (config('cv/' . $lang . '.name') ?? Labels::for($lang)['name'] ?? $lang);
    }

    /** Valid language for a country: the requested one if allowed, otherwise the default. */
    public static function resolveLanguage(string $country, string $lang): string
    {
        $c = self::get($country);
        if (!$c) {
            return 'en';
        }
        return in_array($lang, $c['all_languages'], true) ? $lang : $c['default_language'];
    }
}
