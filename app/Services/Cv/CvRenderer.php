<?php

declare(strict_types=1);

namespace App\Services\Cv;

use App\Services\Labels;

/**
 * Renders a CV (shared content model + template configuration + design settings) to HTML.
 *
 * The same markup serves two consumers:
 *   - "preview": a browser document with an A4-sized page (live editor, thumbnails, gallery);
 *   - "pdf":     the body and CSS given to mPDF, with @page margins and a page background.
 *
 * mPDF understands a subset of CSS, so the markup sticks to what both engines draw the same way:
 * tables for side-by-side content, absolute positioning for sidebars, no flexbox or grid.
 * Headings are followed by tables so mPDF's "keep with table" never leaves a heading alone at the
 * bottom of a page, and entries avoid page breaks inside themselves.
 */
final class CvRenderer
{
    private const SIDEBAR_TYPES = ['skills', 'languages', 'interests', 'hobbies', 'certifications'];
    private const PAGE_W = 210.0;
    private const PAGE_H = 297.0;

    /** @var array<string, mixed> */
    private array $cfg;
    /** @var array<string, mixed> */
    private array $design;
    /** @var array<string, mixed> */
    private array $content;
    private string $lang;
    private string $dir;
    private string $mode;
    private string $start;
    private string $end;
    private string $accent;
    private float $fs;
    private ?string $photoSrc = null;

    /**
     * @param array<string, mixed> $cv       content, design, language_code, country_code
     * @param array<string, mixed> $template a templates row (with `config`)
     * @param array{preview?: string|null, pdf?: string|null} $photo image sources per mode
     * @return array{html: string, body: string, css: string, page: array<string, mixed>, dir: string, lang: string}
     */
    public function render(array $cv, array $template, string $mode = 'preview', array $photo = []): array
    {
        $this->cfg = $template['config'];
        $this->content = $cv['content'];
        $this->lang = (string) $cv['language_code'];
        $this->dir = Labels::dir($this->lang);
        $this->mode = $mode;
        $this->start = $this->dir === 'rtl' ? 'right' : 'left';
        $this->end = $this->dir === 'rtl' ? 'left' : 'right';
        $this->design = $cv['design'];
        $this->accent = (string) $this->design['accent'];
        $this->fs = (float) $this->design['fontSize'];

        $shape = ($this->cfg['photo_shape'] ?? 'square') === 'circle' ? 'circle' : 'square';
        $src = $mode === 'pdf' ? ($photo['pdf_' . $shape] ?? $photo['pdf'] ?? null) : ($photo['preview_' . $shape] ?? $photo['preview'] ?? null);
        $this->photoSrc = ($this->cfg['photo'] ?? false) && !empty($this->design['showPhoto']) && $src ? $src : null;

        $layout = (string) $this->cfg['layout'];
        $page = $this->pageGeometry($layout);
        $body = match ($layout) {
            'sidebar' => $this->sidebarLayout($page),
            'gutter' => $this->gutterLayout(),
            default => $this->singleLayout(),
        };
        $css = $this->css($page);

        $html = '';
        if ($mode !== 'pdf') {
            $html = '<!doctype html><html lang="' . e($this->htmlLang()) . '" dir="' . $this->dir . '"><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($this->content['personal']['fullName'] ?? 'CV') . '</title>'
                . '<style>' . $this->fontFaces() . $css . $this->previewCss($page) . '</style></head>'
                . '<body class="preview-body"><div class="page" id="cv-page">' . $body . '</div></body></html>';
        }
        return ['html' => $html, 'body' => $body, 'css' => $css, 'page' => $page, 'dir' => $this->dir, 'lang' => $this->htmlLang()];
    }

    private function htmlLang(): string
    {
        return match ($this->lang) { 'zh' => 'zh-CN', default => $this->lang };
    }

    // ─────────────────────────────── geometry ───────────────────────────────

    /** @return array<string, mixed> */
    private function pageGeometry(string $layout): array
    {
        $m = (float) (config('design.margins')[$this->design['margins']] ?? 16);
        $g = ['margin' => $m, 'top' => $m, 'bottom' => $m, 'left' => $m, 'right' => $m, 'sidebar' => null];
        if ($layout === 'sidebar') {
            $w = (float) ($this->cfg['sidebar']['width'] ?? 62);
            $side = ($this->design['variant'] ?? 'left') === 'right' ? 'right' : 'left';
            if ($this->dir === 'rtl') {
                $side = $side === 'left' ? 'right' : 'left';
            }
            $g['sidebar'] = ['width' => $w, 'side' => $side, 'style' => $this->cfg['sidebar']['style'] ?? 'light'];
            $g[$side] = $w + 8;
            $g[$side === 'left' ? 'right' : 'left'] = $m;
        }
        return $g;
    }

    // ─────────────────────────────── layouts ────────────────────────────────

    private function singleLayout(): string
    {
        $out = $this->header((string) ($this->design['variant'] ?: $this->cfg['header']));
        foreach ($this->visibleSections() as $section) {
            $out .= $this->section($section);
        }
        return $out;
    }

    private function gutterLayout(): string
    {
        $out = $this->header((string) ($this->design['variant'] ?: $this->cfg['header']));
        $gw = (float) ($this->cfg['gutter'] ?? 34);
        foreach ($this->visibleSections() as $section) {
            $rows = $this->sectionRows($section);
            if ($rows === []) {
                continue;
            }
            $out .= '<table class="gt" cellspacing="0" cellpadding="0">';
            foreach ($rows as $i => $row) {
                $out .= '<tr><td class="gt-h" style="width:' . $gw . 'mm">' . ($i === 0 ? '<div class="gh">' . $this->headingText($section) . '</div>' : '')
                    . '</td><td class="gt-c">' . $row . '</td></tr>';
            }
            $out .= '</table>';
        }
        return $out;
    }

    /** @param array<string, mixed> $page */
    private function sidebarLayout(array $page): string
    {
        $sb = $page['sidebar'];
        $sections = $this->visibleSections();
        $sideSections = [];
        $mainSections = [];
        foreach ($sections as $s) {
            if (in_array($s['type'], self::SIDEBAR_TYPES, true)) {
                $sideSections[] = $s;
            } else {
                $mainSections[] = $s;
            }
        }
        // The sidebar is drawn once, on the first page. Sections that would not fit move to the
        // main column (in order), so no information is ever dropped.
        $capacity = self::PAGE_H - 2 * $page['top'];
        $used = $this->estimateContactHeight($sb['width'] - 12) + ($this->photoSrc ? ($sb['width'] - 22) + 6 : 0);
        $keep = [];
        foreach ($sideSections as $s) {
            $h = $this->estimateSidebarSection($s, $sb['width'] - 12);
            if ($used + $h <= $capacity) {
                $keep[] = $s;
                $used += $h;
            } else {
                $mainSections[] = $s;
            }
        }
        $order = array_flip(array_map(fn ($s) => $s['id'], $sections));
        usort($mainSections, fn ($a, $b) => $order[$a['id']] <=> $order[$b['id']]);

        $inner = $sb['width'] - 12;
        $left = $sb['side'] === 'left' ? 6 : self::PAGE_W - $sb['width'] + 6;
        $side = '<div class="sb" style="position:absolute; left:' . $left . 'mm; top:' . $page['top'] . 'mm; width:' . $inner . 'mm;">';
        if ($this->photoSrc) {
            $size = $inner - 10;
            $side .= '<div class="sb-photo"><img src="' . e($this->photoSrc) . '" style="width:' . $size . 'mm; height:' . $size . 'mm;" alt=""></div>';
        }
        $side .= '<div class="sb-h">' . e(Labels::section($this->lang, 'contact')) . '</div>' . $this->contactLines(true);
        foreach ($keep as $s) {
            $side .= '<div class="sb-h">' . $this->headingText($s, false) . '</div>' . $this->sidebarSectionBody($s);
        }
        $side .= '</div>';

        $main = $this->nameBlock('left');
        foreach ($mainSections as $s) {
            $main .= $this->section($s);
        }
        return $side . '<div class="main">' . $main . '</div>';
    }

    // ─────────────────────────────── header ─────────────────────────────────

    private function header(string $variant): string
    {
        if ($variant === 'photo-right' && !$this->photoSrc) {
            $variant = 'left';
        }
        return match ($variant) {
            'band' => '<div class="hd-band">' . $this->nameBlock('band') . '</div>',
            'split' => '<table class="hd hd-split" cellspacing="0" cellpadding="0"><tr><td class="hd-l">' . $this->photoInline() . $this->nameBlock('left', false)
                . '</td><td class="hd-r">' . $this->contactLines(false) . '</td></tr></table>' . $this->detailsLine(),
            'photo-right' => '<table class="hd" cellspacing="0" cellpadding="0"><tr><td class="hd-l">' . $this->nameBlock('left', false) . $this->contactLines(false)
                . $this->detailsLine() . '</td><td class="hd-photo">' . $this->photoImg(30) . '</td></tr></table>',
            'centered' => '<div class="hd hd-center">' . ($this->photoSrc ? '<div class="hd-photo-c">' . $this->photoImg(26) . '</div>' : '')
                . $this->nameBlock('center') . '</div>',
            default => $this->photoSrc
                ? '<table class="hd" cellspacing="0" cellpadding="0"><tr><td class="hd-photo-l">' . $this->photoImg(26) . '</td><td class="hd-l">' . $this->nameBlock('left') . '</td></tr></table>'
                : '<div class="hd">' . $this->nameBlock('left') . '</div>',
        };
    }

    private function nameBlock(string $align, bool $withContact = true): string
    {
        $p = $this->content['personal'];
        $name = trim((string) ($p['fullName'] ?? ''));
        $nameHtml = $name !== '' ? $this->text($name) : ($this->mode === 'pdf' ? '' : '<span class="ph">' . e(self::placeholderName($this->lang)) . '</span>');
        $cls = 'nm' . ($align === 'center' ? ' tc' : '');
        $out = '<div class="' . $cls . '">' . $nameHtml . '</div>';
        if (trim((string) ($p['headline'] ?? '')) !== '') {
            $out .= '<div class="hl' . ($align === 'center' ? ' tc' : '') . '">' . $this->text($p['headline']) . '</div>';
        }
        if ($withContact && $this->cfg['layout'] !== 'sidebar') {
            $out .= $this->contactInline($align === 'center');
            $out .= $this->detailsLine($align === 'center');
        }
        return $out;
    }

    public static function placeholderName(string $lang): string
    {
        return match ($lang) { 'fr' => 'Votre nom', 'ar' => 'الاسم الكامل', 'zh' => '您的姓名', default => 'Your name' };
    }

    /** @return list<array{label: string, value: string, ltr: bool}> */
    private function contactItems(): array
    {
        $p = $this->content['personal'];
        $items = [];
        $add = function (string $key, string $value) use (&$items): void {
            if (trim($value) !== '') {
                $items[] = ['label' => Labels::label($this->lang, $key), 'value' => $value, 'ltr' => $key !== 'address'];
            }
        };
        $add('email', (string) ($p['email'] ?? ''));
        $phone = trim((string) ($p['phone'] ?? ''));
        $code = trim((string) ($p['phoneCode'] ?? ''));
        $add('phone', $phone !== '' && $code !== '' && !str_starts_with($phone, '+') && !str_starts_with($phone, '00') ? $code . ' ' . ltrim($phone, '0 ') : $phone);
        $comma = $this->lang === 'ar' ? '، ' : ($this->lang === 'zh' ? '，' : ', ');
        $loc = implode($comma, array_filter([trim((string) ($p['city'] ?? '')), trim((string) ($p['country'] ?? ''))]));
        $add('address', trim((string) ($p['address'] ?? '')) !== '' ? $p['address'] . ($loc !== '' ? $comma . $loc : '') : $loc);
        $add('linkedin', self::displayUrl((string) ($p['linkedin'] ?? '')));
        $add('portfolio', self::displayUrl((string) ($p['portfolio'] ?? '')));
        $add('website', self::displayUrl((string) ($p['website'] ?? '')));
        foreach ($p['links'] ?? [] as $link) {
            $v = self::displayUrl((string) ($link['url'] ?? ''));
            if ($v !== '') {
                $items[] = ['label' => (string) ($link['label'] ?: 'Link'), 'value' => $v, 'ltr' => true];
            }
        }
        return $items;
    }

    private function contactInline(bool $center): string
    {
        $items = $this->contactItems();
        if ($items === []) {
            return '';
        }
        $parts = array_map(fn ($i) => $this->ltr($i['value'], $i['ltr']), $items);
        return '<div class="ct' . ($center ? ' tc' : '') . '">' . implode('<span class="dot">&nbsp;&nbsp;·&nbsp;&nbsp;</span>', $parts) . '</div>';
    }

    private function contactLines(bool $sidebar): string
    {
        $out = '';
        foreach ($this->contactItems() as $i) {
            $out .= '<div class="cl">' . ($sidebar ? '<span class="sb-k">' . e($i['label']) . '</span><br>' : '') . $this->ltr($i['value'], $i['ltr']) . '</div>';
        }
        if ($sidebar) {
            foreach ($this->details() as $d) {
                $out .= '<div class="cl"><span class="sb-k">' . e($d['label']) . '</span><br>' . $this->text($d['value']) . '</div>';
            }
        }
        return $out;
    }

    /** Optional personal details (date of birth, nationality…), only when filled in. @return list<array{label: string, value: string}> */
    private function details(): array
    {
        $p = $this->content['personal'];
        $out = [];
        foreach (['dateOfBirth', 'nationality', 'hometown', 'workAuthorization', 'drivingLicense'] as $k) {
            $v = trim((string) ($p[$k] ?? ''));
            if ($v !== '') {
                $out[] = ['label' => Labels::label($this->lang, $k), 'value' => $v];
            }
        }
        return $out;
    }

    private function detailsLine(bool $center = false): string
    {
        $d = $this->details();
        if ($d === []) {
            return '';
        }
        $parts = array_map(fn ($i) => '<span class="dk">' . e($i['label']) . ':</span> ' . $this->text($i['value']), $d);
        return '<div class="dt' . ($center ? ' tc' : '') . '">' . implode('<span class="dot">&nbsp;&nbsp;·&nbsp;&nbsp;</span>', $parts) . '</div>';
    }

    private function photoImg(float $size): string
    {
        return $this->photoSrc ? '<img class="ph-img" src="' . e($this->photoSrc) . '" style="width:' . $size . 'mm; height:' . $size . 'mm;" alt="">' : '';
    }

    private function photoInline(): string
    {
        return '';
    }

    // ─────────────────────────────── sections ───────────────────────────────

    /** @return list<array<string, mixed>> */
    private function visibleSections(): array
    {
        $out = [];
        foreach ($this->content['sections'] ?? [] as $s) {
            if (empty($s['hidden']) && $this->hasContent($s)) {
                $out[] = $s;
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $s */
    private function hasContent(array $s): bool
    {
        if (trim((string) ($s['text'] ?? '')) !== '') {
            return true;
        }
        if (!empty($s['onRequest'])) {
            return true;
        }
        foreach ($s['items'] ?? [] as $item) {
            if (is_string($item) ? trim($item) !== '' : $this->itemHasContent($item)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $item */
    private function itemHasContent(array $item): bool
    {
        foreach ($item as $k => $v) {
            if ($k === 'id' || $k === 'category' || $k === 'current' || $k === 'employmentType') {
                continue;
            }
            if (is_array($v) ? array_filter($v, fn ($x) => trim((string) $x) !== '') !== [] : trim((string) $v) !== '') {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $s */
    private function headingText(array $s, bool $allowBilingual = true): string
    {
        $title = $this->sectionTitle($s, $this->lang);
        $second = $this->cfg['bilingual'][$this->lang] ?? null;
        if ($allowBilingual && ($this->cfg['heading'] ?? '') === 'bilingual' && $second && trim((string) $s['title']) === '') {
            return e($title) . '<span class="h2nd">&nbsp;&nbsp;' . e($this->sectionTitle($s, (string) $second)) . '</span>';
        }
        return e($title);
    }

    /** @param array<string, mixed> $s */
    private function sectionTitle(array $s, string $lang): string
    {
        if (trim((string) ($s['title'] ?? '')) !== '') {
            return (string) $s['title'];
        }
        if ($s['type'] === 'skills' && !empty($this->cfg['skills_title'])) {
            return Labels::label($lang, (string) $this->cfg['skills_title']);
        }
        return Labels::section($lang, (string) $s['type']);
    }

    /** @param array<string, mixed> $s */
    private function section(array $s): string
    {
        $rows = $this->sectionRows($s);
        if ($rows === []) {
            return '';
        }
        // The heading shares a table with the first block, so a page break can never separate them.
        $first = array_shift($rows);
        $out = '<table class="sec" cellspacing="0" cellpadding="0"><tr><td class="sh" role="heading" aria-level="2">' . $this->headingText($s) . '</td></tr>'
            . '<tr><td class="sec-c">' . $first . '</td></tr></table>';
        foreach ($rows as $row) {
            $out .= '<table class="blk" cellspacing="0" cellpadding="0"><tr><td>' . $row . '</td></tr></table>';
        }
        return $out;
    }

    /**
     * Content of a section as a list of blocks; each block becomes a table that never breaks
     * across pages (entries) or a row of the gutter table.
     * @param array<string, mixed> $s
     * @return list<string>
     */
    private function sectionRows(array $s): array
    {
        $kind = CvSchema::SECTION_TYPES[$s['type']]['kind'] ?? 'text';
        $type = (string) $s['type'];
        return match ($kind) {
            'text' => trim((string) $s['text']) !== '' ? [$this->textBlock((string) $s['text'], $type === 'summary')] : [],
            'custom' => $this->customRows($s),
            'skills' => $this->skillsRows($s),
            'languages' => $this->languagesRows($s),
            'tags' => $this->tagsRows($s),
            default => $this->entryRows($s),
        };
    }

    private function textBlock(string $text, bool $summary): string
    {
        $style = $summary ? (string) ($this->cfg['summary'] ?? 'plain') : 'plain';
        return '<div class="tx tx-' . $style . '">' . $this->multiline($text) . '</div>';
    }

    /** @param array<string, mixed> $s @return list<string> */
    private function customRows(array $s): array
    {
        $html = '';
        if (trim((string) ($s['text'] ?? '')) !== '') {
            $html .= '<div class="tx">' . $this->multiline((string) $s['text']) . '</div>';
        }
        $html .= $this->bullets($s['items'] ?? []);
        return $html !== '' ? [$html] : [];
    }

    /** @param array<string, mixed> $s @return list<string> */
    private function entryRows(array $s): array
    {
        $rows = [];
        if ($s['type'] === 'references' && !empty($s['onRequest'])) {
            return ['<div class="tx">' . e(Labels::label($this->lang, 'references_on_request')) . '</div>'];
        }
        foreach ($s['items'] ?? [] as $item) {
            if (!$this->itemHasContent($item)) {
                continue;
            }
            $rows[] = $this->entry((string) $s['type'], $item);
        }
        return $rows;
    }

    /**
     * Normalises any entry type to title / subtitle / meta / date / body.
     * @param array<string, mixed> $i
     */
    private function entry(string $type, array $i): string
    {
        $style = (string) ($this->design['dateStyle'] ?? 'month_year');
        $L = $this->lang;
        $title = $subtitle = $meta = $date = '';
        $desc = (string) ($i['description'] ?? '');
        $bullets = $i['bullets'] ?? [];
        switch ($type) {
            case 'education':
                $title = (string) $i['degree'];
                $subtitle = (string) $i['institution'];
                $meta = implode(' · ', array_filter([(string) $i['field'], (string) $i['location'],
                    trim((string) $i['grade']) !== '' ? Labels::label($L, 'grade') . ': ' . $i['grade'] : '']));
                $date = Labels::range($L, (string) $i['startDate'], (string) $i['endDate'], (bool) $i['current'], $style);
                break;
            case 'certifications':
                $title = (string) $i['name'];
                $subtitle = (string) $i['issuer'];
                $meta = self::displayUrl((string) $i['url']);
                $date = $i['date'] !== '' ? Labels::date($L, (string) $i['date'], $style) : '';
                break;
            case 'projects':
                $title = (string) $i['name'];
                $subtitle = (string) $i['role'];
                $meta = self::displayUrl((string) $i['url']);
                $date = Labels::range($L, (string) $i['startDate'], (string) $i['endDate'], (bool) $i['current'], $style);
                break;
            case 'awards':
                $title = (string) $i['title'];
                $subtitle = (string) $i['issuer'];
                $date = $i['date'] !== '' ? Labels::date($L, (string) $i['date'], $style) : '';
                break;
            case 'publications':
                $title = (string) $i['title'];
                $subtitle = (string) $i['publisher'];
                $meta = self::displayUrl((string) $i['url']);
                $date = $i['date'] !== '' ? Labels::date($L, (string) $i['date'], $style) : '';
                break;
            case 'references':
                $title = (string) $i['name'];
                $subtitle = implode(', ', array_filter([(string) $i['position'], (string) $i['organization']]));
                $meta = implode(' · ', array_filter([(string) $i['email'], (string) $i['phone']]));
                break;
            default: // experience, internships, volunteer
                $title = (string) $i['title'];
                $subtitle = (string) $i['organization'];
                $et = (string) ($i['employmentType'] ?? '');
                $meta = implode(' · ', array_filter([(string) $i['location'], $et !== '' ? (string) (Labels::for($L)['employment_types'][$et] ?? '') : '']));
                $date = Labels::range($L, (string) $i['startDate'], (string) $i['endDate'], (bool) $i['current'], $style);
        }

        $body = '';
        if (trim($desc) !== '') {
            $body .= '<div class="e-desc">' . $this->multiline($desc) . '</div>';
        }
        $body .= $this->bullets(is_array($bullets) ? $bullets : []);

        $entryStyle = (string) ($this->cfg['entry'] ?? 'stacked');
        $titleHtml = '<span class="e-t">' . $this->text($title) . '</span>';
        $subHtml = trim($subtitle) !== '' ? '<span class="e-o">' . $this->text($subtitle) . '</span>' : '';

        if ($entryStyle === 'dates-left') {
            $html = '<table class="en en-dl" cellspacing="0" cellpadding="0"><tr><td class="e-dl" style="width:27mm">' . $this->text($date) . '</td><td class="e-main">'
                . '<div>' . $titleHtml . '</div>' . ($subHtml !== '' ? '<div>' . $subHtml . '</div>' : '')
                . (trim($meta) !== '' ? '<div class="e-m">' . $this->text($meta) . '</div>' : '') . $body . '</td></tr></table>';
            return $html;
        }
        if ($entryStyle === 'compact') {
            $line = $titleHtml . ($subHtml !== '' ? '<span class="e-sep">, </span>' . $subHtml : '') . (trim($meta) !== '' ? '<span class="e-m"> · ' . $this->text($meta) . '</span>' : '');
            return '<table class="en en-c" cellspacing="0" cellpadding="0"><tr><td class="e-main">' . $line . '</td><td class="e-d">' . $this->text($date) . '</td></tr>'
                . ($body !== '' ? '<tr><td colspan="2">' . $body . '</td></tr>' : '') . '</table>';
        }
        // stacked
        $html = '<table class="en" cellspacing="0" cellpadding="0"><tr><td class="e-main">' . $titleHtml . '</td><td class="e-d">' . $this->text($date) . '</td></tr>';
        if ($subHtml !== '' || trim($meta) !== '') {
            $html .= '<tr><td colspan="2" class="e-sub">' . $subHtml . (trim($meta) !== '' ? ($subHtml !== '' ? '<span class="e-m">&nbsp;&nbsp;·&nbsp;&nbsp;</span>' : '') . '<span class="e-m">' . $this->text($meta) . '</span>' : '') . '</td></tr>';
        }
        if ($body !== '') {
            $html .= '<tr><td colspan="2">' . $body . '</td></tr>';
        }
        return $html . '</table>';
    }

    /** @param array<int, mixed> $bullets */
    private function bullets(array $bullets): string
    {
        $items = array_values(array_filter(array_map(fn ($b) => is_string($b) ? trim($b) : '', $bullets), fn ($b) => $b !== ''));
        if ($items === []) {
            return '';
        }
        return '<ul class="bl">' . implode('', array_map(fn ($b) => '<li>' . $this->text($b) . '</li>', $items)) . '</ul>';
    }

    /** @param array<string, mixed> $s @return list<string> */
    private function skillsRows(array $s): array
    {
        $groups = [];
        foreach ($s['items'] ?? [] as $i) {
            if (trim((string) ($i['name'] ?? '')) === '') {
                continue;
            }
            $label = $this->text((string) $i['name']);
            $lvl = Labels::level($this->lang, (string) ($i['level'] ?? ''));
            if ($lvl !== '') {
                $label .= ' <span class="lv">(' . e($lvl) . ')</span>';
            }
            $groups[$i['category'] ?? 'technical'][] = $label;
        }
        if ($groups === []) {
            return [];
        }
        $style = (string) ($this->cfg['skills'] ?? 'inline');
        $sep = '<span class="dot">&nbsp;·&nbsp; </span>';
        $catLabel = fn (string $c) => Labels::label($this->lang, $c);
        if ($style === 'columns') {
            $all = array_merge(...array_values($groups));
            $cols = 3;
            $rows = (int) ceil(count($all) / $cols);
            $html = '<table class="sk-cols" cellspacing="0" cellpadding="0">';
            for ($r = 0; $r < $rows; $r++) {
                $html .= '<tr>';
                for ($c = 0; $c < $cols; $c++) {
                    $v = $all[$c * $rows + $r] ?? '';
                    $html .= '<td style="width:33%">' . ($v !== '' ? '<span class="sq">▪</span> ' . $v : '') . '</td>';
                }
                $html .= '</tr>';
            }
            return [$html . '</table>'];
        }
        if ($style === 'grouped' || count($groups) > 1) {
            $html = '<table class="sk-g" cellspacing="0" cellpadding="0">';
            foreach ($groups as $cat => $items) {
                $html .= '<tr><td class="sk-k">' . e($catLabel((string) $cat)) . '</td><td>' . implode($sep, $items) . '</td></tr>';
            }
            return [$html . '</table>'];
        }
        return ['<div class="tx">' . implode($sep, array_merge(...array_values($groups))) . '</div>'];
    }

    /** @param array<string, mixed> $s @return list<string> */
    private function languagesRows(array $s): array
    {
        $parts = [];
        foreach ($s['items'] ?? [] as $i) {
            if (trim((string) ($i['name'] ?? '')) === '') {
                continue;
            }
            $lvl = Labels::level($this->lang, (string) ($i['level'] ?? ''));
            $parts[] = '<b>' . $this->text((string) $i['name']) . '</b>' . ($lvl !== '' ? ' <span class="lv">— ' . e($lvl) . '</span>' : '');
        }
        return $parts === [] ? [] : ['<div class="tx">' . implode('<span class="dot">&nbsp;&nbsp;·&nbsp;&nbsp;</span>', $parts) . '</div>'];
    }

    /** @param array<string, mixed> $s @return list<string> */
    private function tagsRows(array $s): array
    {
        $parts = [];
        foreach ($s['items'] ?? [] as $i) {
            $n = trim((string) (is_array($i) ? ($i['name'] ?? '') : $i));
            if ($n !== '') {
                $parts[] = $this->text($n);
            }
        }
        return $parts === [] ? [] : ['<div class="tx">' . implode('<span class="dot">&nbsp;·&nbsp; </span>', $parts) . '</div>'];
    }

    /** Narrow-column rendering for the sidebar. @param array<string, mixed> $s */
    private function sidebarSectionBody(array $s): string
    {
        $out = '';
        foreach ($s['items'] ?? [] as $i) {
            $name = trim((string) (is_array($i) ? ($i['name'] ?? '') : $i));
            if ($name === '') {
                continue;
            }
            $extra = '';
            if ($s['type'] === 'skills' || $s['type'] === 'languages') {
                $lvl = Labels::level($this->lang, (string) ($i['level'] ?? ''));
                $extra = $lvl !== '' ? '<br><span class="sb-lv">' . e($lvl) . '</span>' : '';
            } elseif ($s['type'] === 'certifications') {
                $bits = array_filter([(string) ($i['issuer'] ?? ''), ($i['date'] ?? '') !== '' ? Labels::date($this->lang, (string) $i['date'], (string) $this->design['dateStyle']) : '']);
                $extra = $bits ? '<br><span class="sb-lv">' . $this->text(implode(' · ', $bits)) . '</span>' : '';
            }
            $out .= '<div class="sb-i">' . $this->text($name) . $extra . '</div>';
        }
        return $out;
    }

    // ─────────────────────────────── estimates ──────────────────────────────

    private function lineMm(): float
    {
        return $this->fs * 0.3528 * (float) $this->design['lineHeight'];
    }

    private function linesFor(string $text, float $widthMm): int
    {
        $charMm = $this->fs * 0.3528 * 0.52;
        $perLine = max(8, (int) floor($widthMm / $charMm));
        return max(1, (int) ceil(mb_strwidth($text) / $perLine));
    }

    private function estimateContactHeight(float $width): float
    {
        $h = 9;
        foreach ($this->contactItems() as $i) {
            $h += ($this->linesFor($i['value'], $width) + 1) * $this->lineMm() + 1.5;
        }
        foreach ($this->details() as $d) {
            $h += ($this->linesFor($d['value'], $width) + 1) * $this->lineMm() + 1.5;
        }
        return $h;
    }

    /** @param array<string, mixed> $s */
    private function estimateSidebarSection(array $s, float $width): float
    {
        $h = 10;
        foreach ($s['items'] ?? [] as $i) {
            $name = (string) (is_array($i) ? ($i['name'] ?? '') : $i);
            if (trim($name) === '') {
                continue;
            }
            $lines = $this->linesFor($name, $width);
            if (!empty($i['level']) || !empty($i['issuer']) || !empty($i['date'])) {
                $lines++;
            }
            $h += $lines * $this->lineMm() + 1.6;
        }
        return $h;
    }

    // ─────────────────────────────── text helpers ───────────────────────────

    /** Escapes text and adds invisible break opportunities inside very long words and URLs. */
    private function text(string $s): string
    {
        return self::breakLong(e($s));
    }

    /** Keeps e-mails, phone numbers and URLs in left-to-right order inside right-to-left documents. */
    private function ltr(string $s, bool $isLtr = true): string
    {
        if (!$isLtr || $this->dir !== 'rtl' || preg_match('/[\x{0600}-\x{06FF}]/u', $s)) {
            return $this->text($s);
        }
        return '<bdo dir="ltr">' . e($s) . '</bdo>';
    }

    private function multiline(string $s): string
    {
        return nl2br($this->text($s), false);
    }

    public static function breakLong(string $escaped): string
    {
        return (string) preg_replace_callback('/[^\s<>&;]{25,}/u', function (array $m): string {
            $w = $m[0];
            if (preg_match('/[\x{0600}-\x{06FF}\x{4E00}-\x{9FFF}\x{3000}-\x{303F}]/u', $w)) {
                return $w;
            }
            $w = (string) preg_replace('~([/._@?=-])~', "$1\u{200B}", $w);
            return (string) preg_replace('/([^\x{200B}]{18})(?=[^\x{200B}])/u', "$1\u{200B}", $w);
        }, $escaped);
    }

    public static function displayUrl(string $url): string
    {
        return rtrim((string) preg_replace('#^https?://(www\.)?#i', '', trim($url)), '/');
    }

    // ─────────────────────────────── CSS ────────────────────────────────────

    private function fontStack(string $key): string
    {
        if ($this->mode === 'pdf') {
            return $key;
        }
        $font = config('design.fonts.' . $key);
        $family = $font ? "'" . $font['css'] . "'" : "'Lato'";
        $type = $font['type'] ?? 'sans';
        $generic = $type === 'serif' ? 'Georgia, serif' : ($type === 'mono' ? 'Consolas, monospace' : 'Arial, sans-serif');
        return $family . ", 'Noto Naskh Arabic', 'Segoe UI', Tahoma, 'Microsoft YaHei', 'PingFang SC', 'Noto Sans CJK SC', " . $generic;
    }

    private function fontFaces(): string
    {
        $css = '';
        foreach (array_unique([$this->design['font'], $this->cfg['heading_font'] ?? $this->design['font']]) as $key) {
            $font = config('design.fonts.' . $key);
            if (!$font) {
                continue;
            }
            foreach ($font['files'] as $style => $file) {
                $weight = str_contains($style, 'B') ? 700 : 400;
                $italic = str_contains($style, 'I') ? 'italic' : 'normal';
                $css .= "@font-face{font-family:'" . $font['css'] . "';src:url('" . url('/assets/fonts/' . $file) . "') format('truetype');font-weight:$weight;font-style:$italic;font-display:swap}";
            }
        }
        return $css;
    }

    /** @param array<string, mixed> $page */
    private function css(array $page): string
    {
        $a = $this->accent;
        $fs = $this->fs;
        $lh = (float) $this->design['lineHeight'];
        $body = $this->fontStack((string) $this->design['font']);
        $headFont = $this->fontStack((string) ($this->cfg['heading_font'] ?? $this->design['font']));
        $gap = (float) (config('design.spacing')[$this->design['spacing']] ?? 5.5);
        $muted = '#5B6472';
        $rule = '#C9CED6';
        $nameSize = (float) ($this->cfg['name_size'] ?? 22);
        $upper = ($this->cfg['name_case'] ?? 'normal') === 'upper';
        $s = $this->start;
        $en = $this->end;
        $tint = Design::tint($a, 0.9);
        $tintStrong = Design::tint($a, 0.82);
        $entryGap = max(1.6, $gap * 0.45);

        $heading = match ((string) ($this->cfg['heading'] ?? 'rule')) {
            'caps-rule' => "text-transform:uppercase; letter-spacing:0.9pt; font-size:" . ($fs + 0.5) . "pt; color:$a; border-bottom:0.8pt solid $a; padding-bottom:0.8mm;",
            'bar' => "font-size:" . ($fs + 2) . "pt; color:#111827; border-$s:1.3mm solid $a; padding-$s:2.6mm;",
            'band' => "font-size:" . ($fs + 1.5) . "pt; color:$a; background-color:$tint; padding:1.2mm 2.5mm;",
            'accent' => "font-size:" . ($fs + 3) . "pt; color:$a;",
            'double' => "text-transform:uppercase; letter-spacing:1.4pt; font-size:" . ($fs + 0.5) . "pt; color:$a; border-top:0.6pt solid $a; border-bottom:0.6pt solid $a; padding:0.9mm 0; text-align:center;",
            'caps-plain' => "text-transform:uppercase; letter-spacing:1.2pt; font-size:" . ($fs + 0.5) . "pt; color:$a;",
            'bilingual' => "font-size:" . ($fs + 1.5) . "pt; color:$a; border-bottom:0.8pt solid $rule; padding-bottom:0.8mm;",
            default => "font-size:" . ($fs + 2) . "pt; color:$a; border-bottom:0.6pt solid $rule; padding-bottom:0.8mm;",
        };

        $sb = $page['sidebar'];
        $sbText = $sb && $sb['style'] === 'accent' ? '#FFFFFF' : '#1F2937';
        $sbMuted = $sb && $sb['style'] === 'accent' ? Design::tint($a, 0.78) : $muted;
        $sbHead = $sb && $sb['style'] === 'accent' ? '#FFFFFF' : $a;

        return <<<CSS
body { font-family: $body; font-size: {$fs}pt; line-height: $lh; color: #1F2937; direction: {$this->dir}; text-align: $s; }
table { border-collapse: collapse; width: 100%; }
td { padding: 0; vertical-align: top; text-align: $s; }
.tc { text-align: center; }
.nm { font-family: $headFont; font-size: {$nameSize}pt; font-weight: bold; color: #111827; line-height: 1.15; {$this->upper($upper)} }
.hl { font-size: {$this->num($fs + 2)}pt; color: $a; margin-top: 1mm; }
.ct { color: #374151; margin-top: 2.2mm; font-size: {$this->num($fs - 0.5)}pt; }
.dt { color: $muted; margin-top: 1mm; font-size: {$this->num($fs - 1)}pt; }
.dk { color: $muted; }
.dot { color: #9CA3AF; }
.hd { margin-bottom: 1mm; }
.hd-l { vertical-align: middle; }
.hd-r { text-align: $en; vertical-align: bottom; color: #374151; font-size: {$this->num($fs - 0.5)}pt; }
.hd-r .cl { text-align: $en; }
.hd-photo { width: 34mm; text-align: $en; vertical-align: middle; }
.hd-photo-l { width: 31mm; vertical-align: middle; }
.hd-photo-c { text-align: center; margin-bottom: 2mm; }
.hd-band { background-color: $a; color: #FFFFFF; padding: 6mm 7mm; }
.hd-band .nm { color: #FFFFFF; }
.hd-band .hl { color: $tintStrong; }
.hd-band .ct, .hd-band .dt, .hd-band .dk, .hd-band .dot { color: #E5E7EB; }
.cl { margin-bottom: 1mm; }
.cl-k { font-size: {$this->num($fs - 1.5)}pt; color: $muted; text-transform: uppercase; letter-spacing: 0.5pt; }
.ph { color: #9CA3AF; }
table.sec { margin-top: {$gap}mm; page-break-inside: avoid; }
td.sh { font-family: $headFont; font-weight: bold; $heading }
td.sec-c { padding-top: 2mm; }
.h2nd { font-weight: normal; color: $muted; font-size: {$this->num($fs - 0.5)}pt; }
table.blk { margin-top: {$entryGap}mm; page-break-inside: avoid; }
table.en { page-break-inside: avoid; }
.e-t { font-weight: bold; color: #111827; }
.e-o { font-weight: bold; color: $a; }
.e-sub { padding-top: 0.3mm; }
.e-m { color: $muted; font-weight: normal; }
.e-d { text-align: $en; color: $muted; white-space: nowrap; width: 38mm; font-size: {$this->num($fs - 0.5)}pt; }
.e-dl { color: $a; font-size: {$this->num($fs - 0.5)}pt; padding-$en: 3mm; }
.e-desc { margin-top: 0.8mm; }
ul.bl { margin: 0.8mm 0 0 0; padding-$s: 4.5mm; }
ul.bl li { margin-bottom: 0.5mm; }
.tx { }
.tx-italic { font-style: italic; color: #374151; }
.tx-boxed { background-color: $tint; border-$s: 1.2mm solid $a; padding: 2.6mm 3.2mm; }
.lv { color: $muted; font-size: {$this->num($fs - 1)}pt; }
.sq { color: $a; }
table.sk-g td { padding-bottom: 0.9mm; }
td.sk-k { width: 30mm; font-weight: bold; color: #111827; padding-$en: 3mm; }
table.sk-cols td { padding-bottom: 0.9mm; padding-$en: 2mm; }
table.gt { margin-top: {$gap}mm; }
td.gt-h { padding-$en: 4mm; }
td.gt-c { padding-bottom: {$entryGap}mm; }
.gh { font-family: $headFont; font-weight: bold; $heading border: none; padding: 0; background-color: transparent; text-align: $s; letter-spacing: 0.3pt; font-size: {$this->num($fs)}pt; }
.main { }
.sb { color: $sbText; font-size: {$this->num($fs - 0.5)}pt; }
.sb-k { font-size: {$this->num($fs - 1.5)}pt; color: $sbMuted; text-transform: uppercase; letter-spacing: 0.5pt; }
.sb-lv { color: $sbMuted; font-size: {$this->num($fs - 1)}pt; }
.sb-h { font-family: $headFont; font-weight: bold; color: $sbHead; text-transform: uppercase; letter-spacing: 1pt; font-size: {$this->num($fs - 0.5)}pt; margin: 5mm 0 2mm 0; padding-bottom: 0.8mm; border-bottom: 0.6pt solid $sbMuted; }
.sb-i { margin-bottom: 1.6mm; }
.sb-photo { text-align: center; margin-bottom: 2mm; }
CSS;
    }

    private function upper(bool $upper): string
    {
        return $upper ? 'text-transform: uppercase; letter-spacing: 1pt;' : '';
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /** Page box for the browser preview (the PDF uses @page instead). @param array<string, mixed> $page */
    private function previewCss(array $page): string
    {
        $bg = '#FFFFFF';
        if ($page['sidebar']) {
            $sb = $page['sidebar'];
            $color = $sb['style'] === 'accent' ? $this->accent : Design::tint($this->accent, 0.9);
            $w = $sb['width'];
            $bg = $sb['side'] === 'left'
                ? "linear-gradient(to right, $color 0, $color {$w}mm, #FFFFFF {$w}mm)"
                : "linear-gradient(to left, $color 0, $color {$w}mm, #FFFFFF {$w}mm)";
        }
        return "html{background:#E9ECF1}body.preview-body{margin:0;padding:0}"
            . ".page{position:relative;box-sizing:border-box;width:210mm;min-height:297mm;margin:0 auto;background:$bg;"
            . "padding:{$page['top']}mm {$page['right']}mm {$page['bottom']}mm {$page['left']}mm;overflow-wrap:break-word}"
            . ".ph-img{object-fit:cover}"
            . ($this->cfg['photo_shape'] === 'circle' ? '.sb-photo img,.hd-photo img,.hd-photo-l img,.hd-photo-c img{border-radius:50%}' : '')
            . "@media print{html{background:#fff}.page{margin:0}}";
    }

    /** CSS for mPDF's page box (margins are set on the Mpdf object). @param array<string, mixed> $page */
    public function pdfPageCss(array $page, ?string $stripImage): string
    {
        if (!$stripImage) {
            return '';
        }
        return '@page { background: url(' . $stripImage . ') no-repeat 0 0; background-image-resize: 6; }';
    }
}
