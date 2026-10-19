<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Document labels (section titles, months, field names) per document language.
 */
final class Labels
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /** @return array<string, mixed> */
    public static function for(string $lang): array
    {
        if (!in_array($lang, Countries::SUPPORTED_LANGUAGES, true)) {
            $lang = 'en';
        }
        return self::$cache[$lang] ??= require BASE_PATH . '/config/cv/' . $lang . '.php';
    }

    public static function section(string $lang, string $type): string
    {
        return (string) (self::for($lang)['sections'][$type] ?? self::for('en')['sections'][$type] ?? ucfirst($type));
    }

    public static function label(string $lang, string $key): string
    {
        return (string) (self::for($lang)['labels'][$key] ?? self::for('en')['labels'][$key] ?? $key);
    }

    public static function dir(string $lang): string
    {
        return (string) (self::for($lang)['dir'] ?? 'ltr');
    }

    /** Formats "YYYY-MM" or "YYYY" for a CV, e.g. "mars 2021", "Mar 2021", "2021年3月", "2021.03". */
    public static function date(string $lang, string $value, string $style): string
    {
        $value = trim($value);
        if (!preg_match('/^(\d{4})(?:-(\d{2}))?$/', $value, $m)) {
            return $value;
        }
        $year = $m[1];
        $month = isset($m[2]) ? (int) $m[2] : 0;
        $formats = self::for($lang)['date_formats'];
        if ($month < 1 || $month > 12 || $style === 'year') {
            return str_replace('{Y}', $year, (string) $formats['year']);
        }
        $pattern = (string) ($formats[$style] ?? $formats['month_year']);
        return strtr($pattern, [
            '{Y}' => $year,
            '{MM}' => sprintf('%02d', $month),
            '{N}' => (string) $month,
            '{M}' => (string) self::for($lang)['months'][$month - 1],
        ]);
    }

    /** "Mar 2021 – Present", or a single date when only one is known. */
    public static function range(string $lang, string $start, string $end, bool $current, string $style): string
    {
        $from = $start !== '' ? self::date($lang, $start, $style) : '';
        $to = $current ? self::label($lang, 'present') : ($end !== '' ? self::date($lang, $end, $style) : '');
        if ($from !== '' && $to !== '') {
            return $from . ' – ' . $to;
        }
        return $from !== '' ? $from : $to;
    }

    public static function level(string $lang, string $level): string
    {
        $level = trim($level);
        if ($level === '') {
            return '';
        }
        return (string) (self::for($lang)['levels'][$level] ?? $level);
    }
}
