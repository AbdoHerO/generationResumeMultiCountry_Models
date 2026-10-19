<?php

declare(strict_types=1);

namespace App\Services\Cv;

use App\Services\Countries;

/**
 * Per-CV design settings. Defaults come from the template and the country; user changes are
 * validated so a document can never become unreadable (contrast, size and spacing limits).
 */
final class Design
{
    public const KEYS = ['accent', 'font', 'fontSize', 'lineHeight', 'margins', 'spacing', 'showPhoto', 'dateStyle', 'pageTarget', 'variant'];

    /**
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    public static function defaults(array $template, string $country): array
    {
        $cfg = $template['config'];
        $c = Countries::get($country) ?? [];
        return [
            'accent' => (string) $cfg['accent'],
            'font' => (string) $cfg['font'],
            'fontSize' => (float) $cfg['font_size'],
            'lineHeight' => (float) $cfg['line_height'],
            'margins' => (string) $cfg['margins'],
            'spacing' => (string) $cfg['spacing'],
            'showPhoto' => (bool) $cfg['photo'] && ($c['photo'] ?? 'optional') !== 'discouraged',
            'dateStyle' => (string) ($c['date_style'] ?? 'month_year'),
            'pageTarget' => (string) ($c['page_target'] ?? 'auto'),
            'variant' => (string) ($cfg['variants'][0] ?? ''),
        ];
    }

    /**
     * Merges user values over the defaults, keeping only valid ones.
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $input, array $template, string $country): array
    {
        $d = self::defaults($template, $country);
        if (!is_array($input)) {
            return $d;
        }
        $palette = array_keys(config('design.palette', []));
        $accent = strtoupper(trim((string) ($input['accent'] ?? '')));
        if (preg_match('/^#[0-9A-F]{6}$/', $accent) && (in_array($accent, array_map('strtoupper', $palette), true) || self::contrastWithWhite($accent) >= 4.5)) {
            $d['accent'] = $accent;
        }
        $font = (string) ($input['font'] ?? '');
        if (in_array($font, config('design.selectable_fonts', []), true) || $font === $template['config']['font']) {
            $d['font'] = $font;
        }
        if (is_numeric($input['fontSize'] ?? null)) {
            $fs = round((float) $input['fontSize'] * 2) / 2;
            $d['fontSize'] = max((float) config('design.font_size.min'), min((float) config('design.font_size.max'), $fs));
        }
        if (is_numeric($input['lineHeight'] ?? null)) {
            $d['lineHeight'] = round(max((float) config('design.line_height.min'), min((float) config('design.line_height.max'), (float) $input['lineHeight'])), 2);
        }
        if (array_key_exists((string) ($input['margins'] ?? ''), config('design.margins', []))) {
            $d['margins'] = (string) $input['margins'];
        }
        if (array_key_exists((string) ($input['spacing'] ?? ''), config('design.spacing', []))) {
            $d['spacing'] = (string) $input['spacing'];
        }
        if (array_key_exists('showPhoto', $input)) {
            $d['showPhoto'] = (bool) $input['showPhoto'];
        }
        if (in_array($input['dateStyle'] ?? '', config('design.date_styles', []), true)) {
            $d['dateStyle'] = (string) $input['dateStyle'];
        }
        if (in_array($input['pageTarget'] ?? '', config('design.page_targets', []), true)) {
            $d['pageTarget'] = (string) $input['pageTarget'];
        }
        if (in_array($input['variant'] ?? '', $template['config']['variants'] ?? [], true)) {
            $d['variant'] = (string) $input['variant'];
        }
        return $d;
    }

    /** WCAG contrast ratio of a colour against white. */
    public static function contrastWithWhite(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $rgb = array_map(fn (string $c): float => hexdec($c) / 255, str_split($hex, 2));
        $lin = array_map(fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $rgb);
        $l = 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
        return (1.05) / ($l + 0.05);
    }

    /** A pale tint of the accent for backgrounds (mix with white). */
    public static function tint(string $hex, float $amount): string
    {
        $hex = ltrim($hex, '#');
        $out = '#';
        foreach (str_split($hex, 2) as $c) {
            $v = hexdec($c);
            $out .= sprintf('%02X', (int) round($v + (255 - $v) * $amount));
        }
        return $out;
    }
}
