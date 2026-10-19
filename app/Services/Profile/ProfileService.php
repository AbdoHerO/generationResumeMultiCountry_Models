<?php

declare(strict_types=1);

namespace App\Services\Profile;

use App\Core\HttpException;
use App\Models\Profile;
use App\Services\Countries;
use App\Services\Cv\CvSchema;

/**
 * The global profile: the same CV content model as a CV, in three language versions.
 */
final class ProfileService
{
    public const LANGUAGES = ['fr', 'en', 'zh'];
    public const LANGUAGE_NAMES = ['fr' => 'Français', 'en' => 'English', 'zh' => '简体中文'];

    /** Default section order of an empty profile version. */
    private const DEFAULT_ORDER = ['summary', 'experience', 'education', 'skills', 'languages', 'certifications', 'projects', 'interests'];

    /** @return array<string, mixed> */
    public static function emptyContent(): array
    {
        $sections = array_map(fn (string $t) => CvSchema::newSection($t), self::DEFAULT_ORDER);
        $personal = array_fill_keys(array_keys(CvSchema::PERSONAL), '');
        $personal['links'] = [];
        return ['personal' => $personal, 'sections' => $sections];
    }

    /**
     * Validates client data: exactly the three languages, each through CvSchema::sanitize.
     * @return array<string, array<string, mixed>>
     */
    public static function sanitizeData(mixed $data): array
    {
        if (!is_array($data)) {
            throw new HttpException(422, 'The profile is not in the expected format.', [], 'invalid_profile');
        }
        $size = strlen((string) json_encode($data));
        if ($size > 3 * (int) config('app.limits.content_max_bytes', 409600)) {
            throw new HttpException(413, 'This profile is too large to save. Shorten some sections.', [], 'too_large');
        }
        $out = [];
        foreach (self::LANGUAGES as $lang) {
            $out[$lang] = isset($data[$lang]) && is_array($data[$lang]) ? CvSchema::sanitize($data[$lang]) : self::emptyContent();
        }
        return $out;
    }

    /**
     * The profile for the editor: every language present (empty ones created).
     * @return array{data: array<string, array<string, mixed>>, version: int, exists: bool, filled: array<string, bool>}
     */
    public static function load(int $userId): array
    {
        $p = Profile::find($userId);
        $data = [];
        $filled = [];
        foreach (self::LANGUAGES as $lang) {
            $c = $p['data'][$lang] ?? null;
            $data[$lang] = is_array($c) ? $c : self::emptyContent();
            $filled[$lang] = is_array($c) && self::hasContent($c);
        }
        return ['data' => $data, 'version' => $p['version'] ?? 0, 'exists' => $p !== null, 'filled' => $filled];
    }

    /**
     * State for the editor in "profile" or "import" mode (same editor as CVs, without preview).
     * @param array<string, array<string, mixed>> $data
     * @return array<string, mixed>
     */
    public static function editorState(string $mode, string $backupId, array $data, int $version, string $language, bool $profileExists): array
    {
        $countries = [];
        foreach (Countries::all() as $code => $c) {
            $countries[$code] = ['code' => $code, 'name' => $c['name'], 'native' => $c['native'], 'languages' => $c['all_languages'],
                'defaultLanguage' => $c['default_language'], 'phoneCode' => $c['phone_code'], 'phoneExample' => $c['phone_example'],
                'photo' => $c['photo'], 'optionalFields' => $c['optional_fields'], 'tips' => $c['tips'], 'lengthHint' => $c['length_hint'],
                'sectionOrder' => $c['section_order']];
        }
        // A neutral "country" for the profile: every optional detail is offered.
        $countries['XX'] = ['code' => 'XX', 'name' => 'your profile', 'native' => '', 'languages' => self::LANGUAGES, 'defaultLanguage' => 'fr',
            'phoneCode' => '', 'phoneExample' => '6 12 34 56 78', 'photo' => 'optional',
            'optionalFields' => ['dateOfBirth', 'nationality', 'workAuthorization', 'drivingLicense', 'hometown'],
            'tips' => ['Your profile is a master copy: write everything once. Each CV made from it can then hide, reorder or shorten sections.'],
            'lengthHint' => '', 'sectionOrder' => self::DEFAULT_ORDER];
        $labels = [];
        foreach (Countries::SUPPORTED_LANGUAGES as $lang) {
            $l = \App\Services\Labels::for($lang);
            $labels[$lang] = ['name' => $l['name'], 'dir' => $l['dir'], 'sections' => $l['sections'], 'levels' => $l['levels']];
        }
        return [
            'mode' => $mode,
            'cv' => ['id' => $backupId, 'title' => '', 'country' => 'XX', 'language' => $language, 'templateId' => null, 'version' => $version,
                'content' => null, 'design' => (object) [], 'hasPhoto' => false],
            'profile' => ['data' => $data],
            'profileExists' => $profileExists,
            'templates' => [],
            'countries' => $countries,
            'labels' => $labels,
            'design' => ['palette' => [], 'fonts' => [], 'fontSize' => [], 'lineHeight' => [], 'margins' => [], 'spacing' => []],
            'sectionTypes' => array_keys(CvSchema::SECTION_TYPES),
            'uniqueTypes' => CvSchema::UNIQUE_TYPES,
        ];
    }

    /** @return array<string, bool> languages with real content */
    public static function filledLanguages(int $userId): array
    {
        return self::load($userId)['filled'];
    }

    /** @param array<string, mixed> $content */
    public static function hasContent(array $content): bool
    {
        if (trim((string) ($content['personal']['fullName'] ?? '')) !== '') {
            return true;
        }
        foreach ($content['sections'] ?? [] as $s) {
            if (trim((string) ($s['text'] ?? '')) !== '' || !empty($s['items'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Profile content prepared for a new CV: sections in the template's (or country's) order,
     * the others after them, and the country's calling code when none is set.
     * @param list<string> $order
     * @return array<string, mixed>|null null when the profile has no content in this language
     */
    public static function contentForCv(int $userId, string $language, string $country, array $order): ?array
    {
        $profile = self::load($userId);
        if (!in_array($language, self::LANGUAGES, true) || !$profile['filled'][$language]) {
            return null;
        }
        $content = CvSchema::sanitize($profile['data'][$language]);
        $rank = array_flip($order);
        $sections = $content['sections'];
        usort($sections, function (array $a, array $b) use ($rank): int {
            return ($rank[$a['type']] ?? 100) <=> ($rank[$b['type']] ?? 100);
        });
        // Fresh ids: the CV evolves independently of the profile.
        foreach ($sections as &$s) {
            $s['id'] = CvSchema::newId('s');
            foreach ($s['items'] ?? [] as $k => $item) {
                if (is_array($item) && isset($item['id'])) {
                    $s['items'][$k]['id'] = CvSchema::newId('i');
                }
            }
        }
        unset($s);
        $content['sections'] = $sections;
        if (trim((string) $content['personal']['phoneCode']) === '') {
            $content['personal']['phoneCode'] = (string) (Countries::get($country)['phone_code'] ?? '');
        }
        return CvSchema::sanitize($content);
    }
}
