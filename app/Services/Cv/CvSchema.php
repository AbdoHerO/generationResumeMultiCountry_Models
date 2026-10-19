<?php

declare(strict_types=1);

namespace App\Services\Cv;

use App\Core\HttpException;
use App\Services\Countries;

/**
 * The CV content model: one JSON document per CV, shared by every template so users can switch
 * templates without losing anything.
 *
 * {
 *   "personal": { "fullName", "headline", "email", "phoneCode", "phone", "city", "country", "address",
 *                 "linkedin", "portfolio", "website", "links": [{label, url}],
 *                 "dateOfBirth", "nationality", "workAuthorization", "drivingLicense", "hometown" },
 *   "sections": [ { "id", "type", "title", "hidden", "text", "items": [...], "onRequest" } ]
 * }
 *
 * sanitize() is the only way content reaches the database: unknown keys are dropped (no mass
 * assignment), strings are cleaned and truncated, structure is enforced.
 */
final class CvSchema
{
    /** type => kind, item field set */
    public const SECTION_TYPES = [
        'summary' => ['kind' => 'text'],
        'experience' => ['kind' => 'entries', 'fields' => 'job'],
        'education' => ['kind' => 'entries', 'fields' => 'education'],
        'skills' => ['kind' => 'skills'],
        'languages' => ['kind' => 'languages'],
        'certifications' => ['kind' => 'entries', 'fields' => 'certification'],
        'projects' => ['kind' => 'entries', 'fields' => 'project'],
        'internships' => ['kind' => 'entries', 'fields' => 'job'],
        'volunteer' => ['kind' => 'entries', 'fields' => 'job'],
        'awards' => ['kind' => 'entries', 'fields' => 'award'],
        'publications' => ['kind' => 'entries', 'fields' => 'publication'],
        'interests' => ['kind' => 'tags'],
        'hobbies' => ['kind' => 'tags'],
        'references' => ['kind' => 'entries', 'fields' => 'reference'],
        'additional' => ['kind' => 'text'],
        'custom' => ['kind' => 'custom'],
    ];

    /** Sections that may appear only once per CV. */
    public const UNIQUE_TYPES = ['summary', 'experience', 'education', 'skills', 'languages', 'certifications', 'projects',
        'internships', 'volunteer', 'awards', 'publications', 'interests', 'hobbies', 'references', 'additional'];

    /** field => [type, max length]. Types: s (single line), m (multi-line), d (date), b (bool), u (url), e (email), l (bullet list), x (enum) */
    public const FIELDS = [
        'job' => [
            'title' => ['s', 120], 'organization' => ['s', 140], 'location' => ['s', 120], 'employmentType' => ['x', 'employment'],
            'startDate' => ['d', 7], 'endDate' => ['d', 7], 'current' => ['b', 1], 'description' => ['m', 2000], 'bullets' => ['l', 400],
        ],
        'education' => [
            'degree' => ['s', 160], 'institution' => ['s', 160], 'location' => ['s', 120], 'field' => ['s', 160], 'grade' => ['s', 80],
            'startDate' => ['d', 7], 'endDate' => ['d', 7], 'current' => ['b', 1], 'description' => ['m', 1500], 'bullets' => ['l', 400],
        ],
        'certification' => [
            'name' => ['s', 160], 'issuer' => ['s', 140], 'date' => ['d', 7], 'url' => ['u', 300], 'description' => ['m', 600],
        ],
        'project' => [
            'name' => ['s', 160], 'role' => ['s', 120], 'url' => ['u', 300], 'startDate' => ['d', 7], 'endDate' => ['d', 7],
            'current' => ['b', 1], 'description' => ['m', 1500], 'bullets' => ['l', 400],
        ],
        'award' => [
            'title' => ['s', 160], 'issuer' => ['s', 140], 'date' => ['d', 7], 'description' => ['m', 600],
        ],
        'publication' => [
            'title' => ['s', 300], 'publisher' => ['s', 160], 'date' => ['d', 7], 'url' => ['u', 300], 'description' => ['m', 600],
        ],
        'reference' => [
            'name' => ['s', 120], 'position' => ['s', 120], 'organization' => ['s', 140], 'email' => ['e', 190], 'phone' => ['s', 40],
        ],
    ];

    public const PERSONAL = [
        'fullName' => ['s', 120], 'headline' => ['s', 160], 'email' => ['e', 190], 'phoneCode' => ['s', 6], 'phone' => ['s', 40],
        'city' => ['s', 80], 'country' => ['s', 80], 'address' => ['s', 200],
        'linkedin' => ['u', 300], 'portfolio' => ['u', 300], 'website' => ['u', 300],
        'dateOfBirth' => ['s', 40], 'nationality' => ['s', 60], 'workAuthorization' => ['s', 120],
        'drivingLicense' => ['s', 40], 'hometown' => ['s', 80],
    ];

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract', 'freelance', 'internship', 'apprenticeship', 'temporary'];
    public const SKILL_CATEGORIES = ['technical', 'soft', 'tools'];
    public const SKILL_LEVELS = ['', 'beginner', 'intermediate', 'advanced', 'expert'];

    public const MAX_SECTIONS = 30;
    public const MAX_ITEMS = 50;
    public const MAX_BULLETS = 20;

    /**
     * A new, empty CV for a country/language, with the section order of the template or country.
     * @param list<string> $order
     * @return array<string, mixed>
     */
    public static function defaultContent(string $country, string $language, array $order): array
    {
        $c = Countries::get($country);
        $sections = [];
        foreach ($order as $type) {
            if (isset(self::SECTION_TYPES[$type])) {
                $sections[] = self::newSection($type);
            }
        }
        $personal = array_fill_keys(array_keys(self::PERSONAL), '');
        $personal['links'] = [];
        $personal['phoneCode'] = (string) ($c['phone_code'] ?? '');
        return ['personal' => $personal, 'sections' => $sections];
    }

    /** @return array<string, mixed> */
    public static function newSection(string $type): array
    {
        $kind = self::SECTION_TYPES[$type]['kind'];
        $section = ['id' => self::newId('s'), 'type' => $type, 'title' => '', 'hidden' => false];
        if ($kind === 'text') {
            $section['text'] = '';
        } elseif ($kind === 'custom') {
            $section['text'] = '';
            $section['items'] = [];
        } else {
            $section['items'] = [];
        }
        if ($type === 'references') {
            $section['onRequest'] = false;
        }
        return $section;
    }

    public static function newId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(5));
    }

    /**
     * Cleans and validates content coming from a client.
     * @return array<string, mixed>
     * @throws HttpException 422 when the structure is unusable
     */
    public static function sanitize(mixed $content): array
    {
        if (!is_array($content) || !isset($content['personal'], $content['sections']) || !is_array($content['personal']) || !is_array($content['sections']) || !array_is_list($content['sections'])) {
            throw new HttpException(422, 'The CV content is not in the expected format.', ['content' => 'invalid structure'], 'invalid_content');
        }
        if (count($content['sections']) > self::MAX_SECTIONS) {
            throw new HttpException(422, 'A CV can have at most ' . self::MAX_SECTIONS . ' sections.', [], 'too_many_sections');
        }

        $personal = [];
        foreach (self::PERSONAL as $key => [$type, $max]) {
            $personal[$key] = self::cleanValue($content['personal'][$key] ?? '', $type, $max);
        }
        $links = [];
        foreach (array_slice(is_array($content['personal']['links'] ?? null) ? $content['personal']['links'] : [], 0, 5) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $label = self::clean($link['label'] ?? '', 40);
            $url = self::cleanUrl($link['url'] ?? '', 300);
            if ($label !== '' || $url !== '') {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }
        $personal['links'] = $links;

        $sections = [];
        $seenIds = [];
        $seenTypes = [];
        foreach ($content['sections'] as $raw) {
            if (!is_array($raw) || !isset(self::SECTION_TYPES[$raw['type'] ?? ''])) {
                continue; // unknown section types are dropped
            }
            $type = (string) $raw['type'];
            if (in_array($type, self::UNIQUE_TYPES, true) && isset($seenTypes[$type])) {
                continue;
            }
            $seenTypes[$type] = true;
            $section = self::sanitizeSection($type, $raw, $seenIds);
            $sections[] = $section;
        }
        return ['personal' => $personal, 'sections' => $sections];
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, bool> $seenIds
     * @return array<string, mixed>
     */
    private static function sanitizeSection(string $type, array $raw, array &$seenIds): array
    {
        $kind = self::SECTION_TYPES[$type]['kind'];
        $id = self::cleanId($raw['id'] ?? '', 's', $seenIds);
        $section = [
            'id' => $id,
            'type' => $type,
            'title' => self::clean($raw['title'] ?? '', 80),
            'hidden' => (bool) ($raw['hidden'] ?? false),
        ];
        $items = is_array($raw['items'] ?? null) && array_is_list($raw['items']) ? array_slice($raw['items'], 0, self::MAX_ITEMS) : [];

        switch ($kind) {
            case 'text':
                $section['text'] = self::cleanMultiline($raw['text'] ?? '', 3000);
                break;
            case 'custom':
                $section['text'] = self::cleanMultiline($raw['text'] ?? '', 3000);
                $section['items'] = self::cleanList($raw['bullets'] ?? $raw['items'] ?? [], 400);
                break;
            case 'skills':
                $section['items'] = [];
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $cat = (string) ($item['category'] ?? 'technical');
                    $level = (string) ($item['level'] ?? '');
                    $section['items'][] = [
                        'id' => self::cleanId($item['id'] ?? '', 'i', $seenIds),
                        'name' => self::clean($item['name'] ?? '', 80),
                        'category' => in_array($cat, self::SKILL_CATEGORIES, true) ? $cat : 'technical',
                        'level' => in_array($level, self::SKILL_LEVELS, true) ? $level : '',
                    ];
                }
                break;
            case 'languages':
                $section['items'] = [];
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $section['items'][] = [
                        'id' => self::cleanId($item['id'] ?? '', 'i', $seenIds),
                        'name' => self::clean($item['name'] ?? '', 60),
                        'level' => self::clean($item['level'] ?? '', 40),
                    ];
                }
                break;
            case 'tags':
                $section['items'] = [];
                foreach ($items as $item) {
                    $name = is_array($item) ? ($item['name'] ?? '') : $item;
                    $section['items'][] = [
                        'id' => self::cleanId(is_array($item) ? ($item['id'] ?? '') : '', 'i', $seenIds),
                        'name' => self::clean($name, 80),
                    ];
                }
                break;
            default: // entries
                $fields = self::FIELDS[self::SECTION_TYPES[$type]['fields']];
                $section['items'] = [];
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $clean = ['id' => self::cleanId($item['id'] ?? '', 'i', $seenIds)];
                    foreach ($fields as $key => [$t, $max]) {
                        $clean[$key] = self::cleanValue($item[$key] ?? null, $t, is_int($max) ? $max : 0, is_string($max) ? $max : '');
                    }
                    $section['items'][] = $clean;
                }
        }
        if ($type === 'references') {
            $section['onRequest'] = (bool) ($raw['onRequest'] ?? false);
        }
        return $section;
    }

    private static function cleanValue(mixed $value, string $type, int $max, string $enum = ''): mixed
    {
        return match ($type) {
            'b' => (bool) $value,
            'd' => self::cleanDate($value),
            'u' => self::cleanUrl($value, $max),
            'e' => self::clean($value, $max),
            'm' => self::cleanMultiline($value, $max),
            'l' => self::cleanList($value, $max),
            'x' => in_array((string) $value, $enum === 'employment' ? self::EMPLOYMENT_TYPES : [], true) ? (string) $value : '',
            default => self::clean($value, $max),
        };
    }

    public static function clean(mixed $value, int $max): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $s = (string) $value;
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
        // Single line: no control characters, collapse whitespace.
        $s = (string) preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200D}\x{2028}\x{2029}]+/u', ' ', $s);
        $s = trim((string) preg_replace('/\s{2,}/u', ' ', $s));
        return mb_substr($s, 0, $max);
    }

    public static function cleanMultiline(mixed $value, int $max): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $s = str_replace(["\r\n", "\r"], "\n", (string) $value);
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        }
        $s = (string) preg_replace('/[\x00-\x08\x0B-\x1F\x7F]+/u', '', $s);
        $s = (string) preg_replace("/\n{3,}/", "\n\n", $s);
        return mb_substr(trim($s), 0, $max);
    }

    /** @return list<string> */
    public static function cleanList(mixed $value, int $max): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach (array_slice(array_values($value), 0, self::MAX_BULLETS) as $line) {
            $out[] = self::clean(is_array($line) ? ($line['text'] ?? '') : $line, $max);
        }
        return $out;
    }

    public static function cleanDate(mixed $value): string
    {
        $s = is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('/^(\d{4})-(\d{2})$/', $s, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 1900 && (int) $m[1] <= 2100) {
            return $s;
        }
        if (preg_match('/^\d{4}$/', $s) && (int) $s >= 1900 && (int) $s <= 2100) {
            return $s;
        }
        return '';
    }

    /** Keeps http(s) links only; "example.com" becomes "https://example.com". */
    public static function cleanUrl(mixed $value, int $max): string
    {
        $s = self::clean($value, $max);
        if ($s === '') {
            return '';
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $s)) {
            $s = 'https://' . ltrim($s, '/');
        }
        if (!preg_match('#^https?://[^\s<>"]+$#i', $s) || filter_var($s, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        return mb_substr($s, 0, $max);
    }

    /** @param array<string, bool> $seen */
    private static function cleanId(mixed $value, string $prefix, array &$seen): string
    {
        $id = is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $value) ? $value : '';
        if ($id === '' || isset($seen[$id])) {
            $id = self::newId($prefix);
        }
        $seen[$id] = true;
        return $id;
    }

    /**
     * "completed" once the essentials are present, "draft" otherwise.
     * @param array<string, mixed> $content
     */
    public static function status(array $content): string
    {
        $p = $content['personal'] ?? [];
        $hasName = trim((string) ($p['fullName'] ?? '')) !== '';
        $hasContact = trim((string) ($p['email'] ?? '')) !== '' || trim((string) ($p['phone'] ?? '')) !== '';
        $hasBody = false;
        $hasSummaryOrSkills = false;
        foreach ($content['sections'] ?? [] as $s) {
            if (!empty($s['hidden'])) {
                continue;
            }
            if (in_array($s['type'], ['experience', 'education', 'internships'], true)) {
                foreach ($s['items'] ?? [] as $i) {
                    if (trim((string) ($i['title'] ?? $i['degree'] ?? '')) !== '') {
                        $hasBody = true;
                    }
                }
            }
            if ($s['type'] === 'summary' && trim((string) ($s['text'] ?? '')) !== '') {
                $hasSummaryOrSkills = true;
            }
            if ($s['type'] === 'skills' && count(array_filter($s['items'] ?? [], fn ($i) => trim((string) ($i['name'] ?? '')) !== '')) >= 3) {
                $hasSummaryOrSkills = true;
            }
        }
        return $hasName && $hasContact && $hasBody && $hasSummaryOrSkills ? 'completed' : 'draft';
    }

    /** Percentage of key fields filled in (for the editor's progress hint). @param array<string, mixed> $content */
    public static function completeness(array $content): int
    {
        $checks = [];
        $p = $content['personal'] ?? [];
        $checks[] = trim((string) ($p['fullName'] ?? '')) !== '';
        $checks[] = trim((string) ($p['headline'] ?? '')) !== '';
        $checks[] = trim((string) ($p['email'] ?? '')) !== '';
        $checks[] = trim((string) ($p['phone'] ?? '')) !== '';
        $byType = [];
        foreach ($content['sections'] ?? [] as $s) {
            $byType[$s['type']] = $s;
        }
        $checks[] = trim((string) ($byType['summary']['text'] ?? '')) !== '';
        $checks[] = !empty($byType['experience']['items']) || !empty($byType['internships']['items']);
        $checks[] = !empty($byType['education']['items']);
        $checks[] = count($byType['skills']['items'] ?? []) >= 3;
        $checks[] = !empty($byType['languages']['items']);
        return (int) round(100 * count(array_filter($checks)) / count($checks));
    }
}
