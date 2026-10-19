<?php

declare(strict_types=1);

namespace App\Services\Cv;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\Cv;
use App\Models\Setting;
use App\Models\Template;
use App\Services\Countries;
use App\Services\PhotoService;

/**
 * CV business rules shared by the pages and the JSON API.
 */
final class CvService
{
    /** @return array<string, mixed> the new CV row */
    public static function create(int $userId, string $country, string $language, ?string $templateSlug, string $title = ''): array
    {
        $country = strtoupper($country);
        if (!Countries::exists($country)) {
            throw new HttpException(422, 'Please choose one of the available countries.', ['country' => 'invalid']);
        }
        $max = (int) Setting::get('max_cvs_per_user', config('app.limits.cvs_per_user', 50));
        if (Cv::countForUser($userId) >= $max) {
            throw new HttpException(422, "You have reached the limit of $max CVs. Delete one to create another.", [], 'limit');
        }
        $template = $templateSlug ? Template::findBySlug($templateSlug) : null;
        if (!$template || !$template['is_active'] || $template['country_code'] !== $country) {
            $template = Template::defaultFor($country);
        }
        if (!$template) {
            throw new HttpException(422, 'No template is available for this country yet.');
        }
        $language = Countries::resolveLanguage($country, $language);
        $c = Countries::get($country);
        $order = $template['config']['section_order'] ?? $c['section_order'];
        $content = CvSchema::defaultContent($country, $language, $order);
        $design = Design::defaults($template, $country);
        $title = CvSchema::clean($title, 120);
        if ($title === '') {
            $title = 'CV · ' . $c['name'] . ' (' . Countries::languageName($language) . ')';
        }
        $id = Cv::create($userId, $title, $country, $language, $template['id'], $content, $design);
        return (array) Cv::findOwned($id, $userId);
    }

    /**
     * Validates and stores an editor save. Returns the new version, or throws 409 when the CV was
     * changed elsewhere since the client loaded it.
     *
     * @param array<string, mixed> $cv  the stored CV (already ownership-checked)
     * @param array<string, mixed> $in  client payload
     * @return array<string, mixed>
     */
    public static function save(array $cv, array $in): array
    {
        $country = strtoupper((string) ($in['country'] ?? $cv['country_code']));
        if (!Countries::exists($country, false)) {
            throw new HttpException(422, 'Unknown country.', ['country' => 'invalid']);
        }
        $language = (string) ($in['language'] ?? $cv['language_code']);
        $languageOk = in_array($language, Countries::get($country)['all_languages'], true);
        if (!$languageOk) {
            throw new HttpException(422, 'This language is not available for the selected country.', ['language' => 'invalid']);
        }
        $templateId = (int) ($in['templateId'] ?? $cv['template_id']);
        $template = Template::find($templateId);
        $isCurrent = $templateId === (int) $cv['template_id'];
        if (!$template || $template['country_code'] !== $country || (!$template['is_active'] && !$isCurrent)) {
            throw new HttpException(422, 'This template is not available for the selected country.', ['templateId' => 'invalid']);
        }

        $encodedSize = strlen((string) json_encode($in['content'] ?? null));
        if ($encodedSize > (int) config('app.limits.content_max_bytes', 409600)) {
            throw new HttpException(413, 'This CV is too large to save. Shorten some sections.', [], 'too_large');
        }
        $content = CvSchema::sanitize($in['content'] ?? null);
        $design = Design::sanitize($in['design'] ?? [], $template, $country);
        $title = CvSchema::clean($in['title'] ?? $cv['title'], 120);
        if ($title === '') {
            $title = (string) $cv['title'];
        }
        $status = CvSchema::status($content);
        $version = Cv::saveContent((int) $cv['id'], (int) $cv['user_id'], (int) ($in['version'] ?? 0), $content, $design, $title, $country, $language, $templateId, $status);
        if ($version === null) {
            throw new HttpException(409, 'This CV was changed in another tab or window. Reload to get the latest version.', [], 'version_conflict');
        }
        return ['version' => $version, 'status' => $status, 'completeness' => CvSchema::completeness($content), 'content' => $content, 'design' => $design, 'title' => $title];
    }

    /** @param array<string, mixed> $cv @return array<string, mixed> */
    public static function duplicate(array $cv, int $userId): array
    {
        $max = (int) Setting::get('max_cvs_per_user', config('app.limits.cvs_per_user', 50));
        if (Cv::countForUser($userId) >= $max) {
            throw new HttpException(422, "You have reached the limit of $max CVs. Delete one to create another.", [], 'limit');
        }
        return Database::transaction(function () use ($cv, $userId): array {
            $title = mb_substr('Copy of ' . $cv['title'], 0, 120);
            $id = Cv::create($userId, $title, (string) $cv['country_code'], (string) $cv['language_code'], (int) $cv['template_id'], $cv['content'], $cv['design'], (string) $cv['status']);
            if (!empty($cv['photo_path'])) {
                $copy = PhotoService::copy((string) $cv['photo_path']);
                if ($copy) {
                    Cv::setPhoto($id, $userId, $copy);
                }
            }
            return (array) Cv::findOwned($id, $userId);
        });
    }

    /** @param array<string, mixed> $cv */
    public static function delete(array $cv): void
    {
        Cv::delete((int) $cv['id'], (int) $cv['user_id']);
        if (!empty($cv['photo_path']) && !Cv::photoInUse((string) $cv['photo_path'])) {
            PhotoService::delete((string) $cv['photo_path']);
        }
    }

    /**
     * Photo sources for rendering: an authenticated URL for browser previews, file paths for PDF.
     * @param array<string, mixed> $cv
     * @return array<string, string|null>
     */
    public static function photoSources(array $cv): array
    {
        $id = (string) ($cv['photo_path'] ?? '');
        if ($id === '' || PhotoService::path($id) === null) {
            return [];
        }
        $v = substr(md5($id), 0, 8);
        return [
            'preview_square' => url('/cvs/' . $cv['id'] . '/photo') . '?v=' . $v,
            'preview_circle' => url('/cvs/' . $cv['id'] . '/photo') . '?shape=circle&v=' . $v,
            'pdf_square' => PhotoService::path($id),
            'pdf_circle' => PhotoService::path($id, 'circle') ?? PhotoService::path($id),
        ];
    }

    /**
     * Everything the editor needs to start, as one JSON document.
     * @param array<string, mixed> $cv
     * @return array<string, mixed>
     */
    public static function editorState(array $cv): array
    {
        $templates = [];
        foreach (Template::all(false) as $t) {
            if (!$t['is_active'] && $t['id'] !== (int) $cv['template_id']) {
                continue;
            }
            $templates[] = [
                'id' => $t['id'], 'slug' => $t['slug'], 'name' => $t['name'], 'country' => $t['country_code'],
                'category' => $t['category'], 'languages' => $t['languages'], 'recommended' => $t['is_recommended'],
                'photo' => (bool) ($t['config']['photo'] ?? false), 'variants' => $t['config']['variants'] ?? [],
                'layout' => $t['config']['layout'], 'defaults' => Design::defaults($t, $t['country_code']),
                'sectionOrder' => $t['config']['section_order'] ?? null,
            ];
        }
        $countries = [];
        foreach (Countries::all() as $code => $c) {
            if (!$c['enabled'] && $code !== $cv['country_code']) {
                continue;
            }
            $countries[$code] = [
                'code' => $code, 'name' => $c['name'], 'native' => $c['native'], 'languages' => $c['all_languages'],
                'defaultLanguage' => $c['default_language'], 'phoneCode' => $c['phone_code'], 'phoneExample' => $c['phone_example'],
                'photo' => $c['photo'], 'optionalFields' => $c['optional_fields'], 'tips' => $c['tips'],
                'lengthHint' => $c['length_hint'], 'sectionOrder' => $c['section_order'],
            ];
        }
        $labels = [];
        foreach (Countries::SUPPORTED_LANGUAGES as $lang) {
            $l = \App\Services\Labels::for($lang);
            $labels[$lang] = ['name' => $l['name'], 'dir' => $l['dir'], 'sections' => $l['sections'], 'levels' => $l['levels']];
        }
        $fonts = [];
        foreach (config('design.selectable_fonts', []) as $key) {
            $fonts[] = ['key' => $key, 'label' => config('design.fonts.' . $key . '.label')];
        }
        return [
            'cv' => [
                'id' => $cv['id'], 'title' => $cv['title'], 'country' => $cv['country_code'], 'language' => $cv['language_code'],
                'templateId' => $cv['template_id'], 'version' => $cv['version'], 'status' => $cv['status'],
                'content' => $cv['content'], 'design' => $cv['design'], 'hasPhoto' => !empty($cv['photo_path']),
                'updatedAt' => $cv['updated_at'],
            ],
            'templates' => $templates,
            'countries' => $countries,
            'labels' => $labels,
            'design' => [
                'palette' => config('design.palette'), 'fonts' => $fonts, 'fontSize' => config('design.font_size'),
                'lineHeight' => config('design.line_height'), 'margins' => array_keys(config('design.margins')),
                'spacing' => array_keys(config('design.spacing')),
            ],
            'sectionTypes' => array_keys(CvSchema::SECTION_TYPES),
            'uniqueTypes' => CvSchema::UNIQUE_TYPES,
            'completeness' => CvSchema::completeness($cv['content']),
        ];
    }
}
