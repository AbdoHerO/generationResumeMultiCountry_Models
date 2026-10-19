<?php

declare(strict_types=1);

/*
 * The 25 country templates. Seeded into the `templates` table by TemplateSeeder.
 *
 * Name, description, category, recommended flag, availability and order are administrator-
 * managed once seeded. `config` is code-owned: it drives App\Services\Cv\CvRenderer and is
 * refreshed on every seed.
 *
 * config keys
 *   layout      single | sidebar | gutter
 *   header      centered | left | band | split | photo-right   (single and gutter layouts)
 *   heading     rule | caps-rule | bar | band | accent | double | caps-plain | bilingual
 *   entry       stacked | dates-left | compact
 *   skills      inline | columns | grouped
 *   font, heading_font   keys of config/design.php fonts
 *   accent, font_size (pt), line_height, margins, spacing
 *   photo       whether the template can show a photo; photo_shape square | circle
 *   name_case   normal | upper;  name_size (pt)
 *   sidebar     ['width' => mm, 'style' => light | accent]       (sidebar layout)
 *   gutter      mm width of the heading column                     (gutter layout)
 *   summary     plain | italic | boxed
 *   section_order   default order for a new CV made with this template
 *   variants    layout variants the user may switch between in the design panel
 */

$t = static fn (array $common, array $config): array => $common + ['config' => $config];

return [
    // ───────────── France ─────────────
    $t(['slug' => 'fr-classic', 'name' => 'France Classic', 'country' => 'FR', 'category' => 'classic', 'recommended' => true,
        'description' => 'Simple, sober and ATS-friendly. A centred header, clear section rules and reverse-chronological entries: the safe choice for any French application.'], [
        'layout' => 'single', 'header' => 'centered', 'heading' => 'caps-rule', 'entry' => 'stacked', 'skills' => 'grouped',
        'font' => 'lato', 'accent' => '#1F3A5F', 'font_size' => 10.5, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'variants' => ['centered', 'left'],
    ]),
    $t(['slug' => 'fr-modern', 'name' => 'France Modern', 'country' => 'FR', 'category' => 'modern', 'recommended' => true,
        'description' => 'Contemporary typography, generous spacing and accent bars on headings, with an optional photo beside the name.'], [
        'layout' => 'single', 'header' => 'photo-right', 'heading' => 'bar', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'firasans', 'accent' => '#0F766E', 'font_size' => 10.5, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'relaxed',
        'photo' => true, 'photo_shape' => 'circle', 'name_case' => 'normal', 'name_size' => 25, 'summary' => 'plain',
        'variants' => ['photo-right', 'left'],
    ]),
    $t(['slug' => 'fr-executive', 'name' => 'France Executive', 'country' => 'FR', 'category' => 'executive', 'recommended' => false,
        'description' => 'A polished layout for senior profiles: a full-width header band, serif typography and an emphasised executive summary.'], [
        'layout' => 'single', 'header' => 'band', 'heading' => 'double', 'entry' => 'stacked', 'skills' => 'columns',
        'font' => 'ptserif', 'heading_font' => 'ptserif', 'accent' => '#1F3A5F', 'font_size' => 10.5, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'upper', 'name_size' => 21, 'summary' => 'italic',
        'variants' => ['band', 'centered'],
    ]),
    $t(['slug' => 'fr-creative', 'name' => 'France Creative', 'country' => 'FR', 'category' => 'creative', 'recommended' => false,
        'description' => 'A coloured side column with photo, contact details and skills: a tasteful visual identity for design, marketing and communication roles.'], [
        'layout' => 'sidebar', 'heading' => 'accent', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'firasans', 'accent' => '#9F1239', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'circle', 'name_case' => 'normal', 'name_size' => 24, 'summary' => 'plain',
        'sidebar' => ['width' => 64, 'style' => 'accent'],
        'variants' => ['left', 'right'],
    ]),
    $t(['slug' => 'fr-compact', 'name' => 'France Compact', 'country' => 'FR', 'category' => 'compact', 'recommended' => false,
        'description' => 'Space-efficient: section titles in a narrow left column and tight spacing, so a rich career still fits on one page.'], [
        'layout' => 'gutter', 'header' => 'split', 'heading' => 'caps-plain', 'entry' => 'compact', 'skills' => 'inline',
        'font' => 'ptsans', 'accent' => '#334155', 'font_size' => 9.5, 'line_height' => 1.3, 'margins' => 'narrow', 'spacing' => 'compact',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 20, 'summary' => 'plain',
        'gutter' => 38,
        'variants' => ['split', 'left'],
    ]),
    $t(['slug' => 'fr-elegance', 'name' => 'France Élégance', 'country' => 'FR', 'category' => 'elegant', 'recommended' => false,
        'description' => 'Refined serif typography, a centred portrait, marker headings and a timeline of your career: graceful and very readable.'], [
        'layout' => 'single', 'header' => 'centered', 'heading' => 'marker', 'entry' => 'timeline', 'skills' => 'columns',
        'font' => 'plexserif', 'heading_font' => 'plexserif', 'accent' => '#6D28D9', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'circle', 'name_case' => 'normal', 'name_size' => 25, 'summary' => 'italic',
        'variants' => ['centered', 'left'],
    ]),
    $t(['slug' => 'fr-horizon', 'name' => 'France Horizon', 'country' => 'FR', 'category' => 'creative', 'recommended' => false,
        'description' => 'A deep-coloured side column with your photo and skills, and bold solid section bands: striking yet professional.'], [
        'layout' => 'sidebar', 'heading' => 'solid', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'lato', 'accent' => '#0F766E', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'circle', 'name_case' => 'normal', 'name_size' => 25, 'summary' => 'plain',
        'sidebar' => ['width' => 66, 'style' => 'dark'],
        'variants' => ['left', 'right'],
    ]),
    $t(['slug' => 'fr-atelier', 'name' => 'France Atelier', 'country' => 'FR', 'category' => 'modern', 'recommended' => false,
        'description' => 'A warm tinted header card with your photo, a highlighted profile box and dates in a margin column.'], [
        'layout' => 'single', 'header' => 'tinted', 'heading' => 'marker', 'entry' => 'dates-left', 'skills' => 'grouped',
        'font' => 'firasans', 'accent' => '#B45309', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 24, 'summary' => 'boxed',
        'variants' => ['tinted', 'left'],
    ]),

    // ───────────── Morocco ─────────────
    $t(['slug' => 'ma-essential', 'name' => 'Morocco Essential', 'country' => 'MA', 'category' => 'classic', 'recommended' => true,
        'description' => 'Simple, professional and clean, with an optional photo. Works in French, Arabic (right-to-left) and English.'], [
        'layout' => 'single', 'header' => 'centered', 'heading' => 'rule', 'entry' => 'stacked', 'skills' => 'grouped',
        'font' => 'lato', 'accent' => '#9F1239', 'font_size' => 10.5, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'variants' => ['centered', 'left'],
    ]),
    $t(['slug' => 'ma-modern', 'name' => 'Morocco Modern', 'country' => 'MA', 'category' => 'modern', 'recommended' => false,
        'description' => 'A light side column for contact details, skills and languages next to a clean main column. Mirrors automatically for Arabic.'], [
        'layout' => 'sidebar', 'heading' => 'bar', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'ptsans', 'accent' => '#0F766E', 'font_size' => 10.5, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'circle', 'name_case' => 'normal', 'name_size' => 24, 'summary' => 'plain',
        'sidebar' => ['width' => 62, 'style' => 'light'],
        'variants' => ['left', 'right'],
    ]),
    $t(['slug' => 'ma-academic', 'name' => 'Morocco Academic', 'country' => 'MA', 'category' => 'academic', 'recommended' => false,
        'description' => 'Education and qualifications first, with dates in a margin column and room for theses, publications and certifications.'], [
        'layout' => 'single', 'header' => 'centered', 'heading' => 'caps-plain', 'entry' => 'dates-left', 'skills' => 'columns',
        'font' => 'plexserif', 'heading_font' => 'plexserif', 'accent' => '#4338CA', 'font_size' => 10.5, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 21, 'summary' => 'plain',
        'section_order' => ['summary', 'education', 'certifications', 'publications', 'experience', 'skills', 'languages'],
        'variants' => ['centered', 'left'],
    ]),
    $t(['slug' => 'ma-bilingual', 'name' => 'Morocco Bilingual', 'country' => 'MA', 'category' => 'bilingual', 'recommended' => true,
        'description' => 'Section titles in French and Arabic side by side; your own text stays exactly as you write it, in either language.'], [
        'layout' => 'single', 'header' => 'split', 'heading' => 'bilingual', 'entry' => 'stacked', 'skills' => 'grouped',
        'font' => 'lato', 'accent' => '#047857', 'font_size' => 10.5, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'bilingual' => ['fr' => 'ar', 'ar' => 'fr', 'en' => 'ar'],
        'languages' => ['fr', 'ar'],
        'variants' => ['split', 'centered'],
    ]),
    $t(['slug' => 'ma-career', 'name' => 'Morocco Career', 'country' => 'MA', 'category' => 'career', 'recommended' => false,
        'description' => 'A balanced timeline for early-career candidates: education, internships and projects with dates in a margin column.'], [
        'layout' => 'single', 'header' => 'left', 'heading' => 'band', 'entry' => 'dates-left', 'skills' => 'inline',
        'font' => 'firasans', 'accent' => '#B45309', 'font_size' => 10.5, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'circle', 'name_case' => 'normal', 'name_size' => 23, 'summary' => 'plain',
        'section_order' => ['summary', 'education', 'internships', 'experience', 'projects', 'skills', 'languages', 'interests'],
        'variants' => ['left', 'photo-right'],
    ]),
    $t(['slug' => 'ma-prestige', 'name' => 'Morocco Prestige', 'country' => 'MA', 'category' => 'executive', 'recommended' => false,
        'description' => 'A navy header band, serif typography and a career timeline: an executive look for senior and management roles.'], [
        'layout' => 'single', 'header' => 'band', 'heading' => 'marker', 'entry' => 'timeline', 'skills' => 'columns',
        'font' => 'ptserif', 'heading_font' => 'ptserif', 'accent' => '#1F3A5F', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'upper', 'name_size' => 22, 'summary' => 'italic',
        'variants' => ['band', 'centered'],
    ]),
    $t(['slug' => 'ma-atlas', 'name' => 'Morocco Atlas', 'country' => 'MA', 'category' => 'creative', 'recommended' => false,
        'description' => 'An emerald side column with photo, contact details and languages, and solid section bands in the main column.'], [
        'layout' => 'sidebar', 'heading' => 'solid', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'lato', 'accent' => '#047857', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'circle', 'name_case' => 'normal', 'name_size' => 24, 'summary' => 'plain',
        'sidebar' => ['width' => 64, 'style' => 'dark'],
        'variants' => ['left', 'right'],
    ]),
    $t(['slug' => 'ma-sahara', 'name' => 'Morocco Sahara', 'country' => 'MA', 'category' => 'modern', 'recommended' => false,
        'description' => 'Warm amber tones, a tinted header card with photo and a framed profile: friendly, modern and easy to scan.'], [
        'layout' => 'single', 'header' => 'tinted', 'heading' => 'solid', 'entry' => 'stacked', 'skills' => 'grouped',
        'font' => 'firasans', 'accent' => '#B45309', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 24, 'summary' => 'boxed',
        'variants' => ['tinted', 'left'],
    ]),

    // ───────────── United States ─────────────
    $t(['slug' => 'us-ats-classic', 'name' => 'US ATS Classic', 'country' => 'US', 'category' => 'classic', 'recommended' => true,
        'description' => 'Single column, conventional headings and plain text: highly readable by people and applicant tracking systems. No photo.'], [
        'layout' => 'single', 'header' => 'centered', 'heading' => 'caps-rule', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'ptserif', 'heading_font' => 'ptserif', 'accent' => '#111827', 'font_size' => 10.5, 'line_height' => 1.3, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'upper', 'name_size' => 20, 'summary' => 'plain',
        'variants' => ['centered', 'left'],
    ]),
    $t(['slug' => 'us-modern', 'name' => 'US Modern', 'country' => 'US', 'category' => 'modern', 'recommended' => false,
        'description' => 'A structured, contemporary resume with coloured headings, right-aligned dates and a skills grid.'], [
        'layout' => 'single', 'header' => 'left', 'heading' => 'accent', 'entry' => 'stacked', 'skills' => 'columns',
        'font' => 'firasans', 'accent' => '#1D4ED8', 'font_size' => 10.5, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 26, 'summary' => 'plain',
        'variants' => ['left', 'split'],
    ]),
    $t(['slug' => 'us-executive', 'name' => 'US Executive', 'country' => 'US', 'category' => 'executive', 'recommended' => false,
        'description' => 'Leadership-focused: a boxed executive summary, core competencies and serif typography for senior roles.'], [
        'layout' => 'single', 'header' => 'left', 'heading' => 'double', 'entry' => 'stacked', 'skills' => 'columns',
        'font' => 'plexserif', 'heading_font' => 'plexserif', 'accent' => '#1F3A5F', 'font_size' => 10.5, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'upper', 'name_size' => 22, 'summary' => 'boxed',
        'section_order' => ['summary', 'skills', 'experience', 'education', 'certifications', 'awards'],
        'variants' => ['left', 'centered'],
    ]),
    $t(['slug' => 'us-technical', 'name' => 'US Technical', 'country' => 'US', 'category' => 'technical', 'recommended' => true,
        'description' => 'For engineers and developers: grouped technical skills at the top, projects up front and monospace accents.'], [
        'layout' => 'single', 'header' => 'split', 'heading' => 'caps-rule', 'entry' => 'stacked', 'skills' => 'grouped',
        'font' => 'ptsans', 'heading_font' => 'plexmono', 'accent' => '#0F766E', 'font_size' => 10, 'line_height' => 1.35, 'margins' => 'narrow', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'section_order' => ['summary', 'skills', 'experience', 'projects', 'education', 'certifications'],
        'variants' => ['split', 'left'],
    ]),
    $t(['slug' => 'us-entry-level', 'name' => 'US Entry-Level', 'country' => 'US', 'category' => 'entry-level', 'recommended' => false,
        'description' => 'Education and transferable skills first, then projects, internships and activities: ideal for students and new graduates.'], [
        'layout' => 'single', 'header' => 'centered', 'heading' => 'bar', 'entry' => 'compact', 'skills' => 'inline',
        'font' => 'lato', 'accent' => '#4338CA', 'font_size' => 10.5, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'section_order' => ['summary', 'education', 'skills', 'projects', 'internships', 'experience', 'volunteer'],
        'variants' => ['centered', 'left'],
    ]),
    $t(['slug' => 'us-impact', 'name' => 'US Impact', 'country' => 'US', 'category' => 'modern', 'recommended' => false,
        'description' => 'A blue header card, a highlighted summary and marker headings that put measurable results front and centre. No photo.'], [
        'layout' => 'single', 'header' => 'tinted', 'heading' => 'marker', 'entry' => 'stacked', 'skills' => 'columns',
        'font' => 'firasans', 'accent' => '#1D4ED8', 'font_size' => 10.5, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 24, 'summary' => 'boxed',
        'variants' => ['tinted', 'left'],
    ]),
    $t(['slug' => 'us-nova', 'name' => 'US Nova', 'country' => 'US', 'category' => 'creative', 'recommended' => false,
        'description' => 'A deep indigo side column for contact details, skills and languages; solid section bands keep the main column crisp.'], [
        'layout' => 'sidebar', 'heading' => 'solid', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'lato', 'accent' => '#4338CA', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 25, 'summary' => 'plain',
        'sidebar' => ['width' => 62, 'style' => 'dark'],
        'variants' => ['left', 'right'],
    ]),
    $t(['slug' => 'us-chronicle', 'name' => 'US Chronicle', 'country' => 'US', 'category' => 'executive', 'recommended' => false,
        'description' => 'Serif typography and a clean career timeline with dates in the margin: classic credibility with a modern rhythm.'], [
        'layout' => 'single', 'header' => 'left', 'heading' => 'caps-rule', 'entry' => 'timeline', 'skills' => 'grouped',
        'font' => 'plexserif', 'heading_font' => 'plexserif', 'accent' => '#0F766E', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'upper', 'name_size' => 22, 'summary' => 'plain',
        'variants' => ['left', 'centered'],
    ]),

    // ───────────── Canada ─────────────
    $t(['slug' => 'ca-professional', 'name' => 'Canada Professional', 'country' => 'CA', 'category' => 'classic', 'recommended' => true,
        'description' => 'Clean, simple and versatile, in English or French. A left-aligned header and quiet rules keep the focus on your accomplishments.'], [
        'layout' => 'single', 'header' => 'left', 'heading' => 'rule', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'lato', 'accent' => '#9F1239', 'font_size' => 10.5, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'variants' => ['left', 'centered'],
    ]),
    $t(['slug' => 'ca-modern', 'name' => 'Canada Modern', 'country' => 'CA', 'category' => 'modern', 'recommended' => true,
        'description' => 'Modern typography with section titles in a left column and restrained colour accents.'], [
        'layout' => 'gutter', 'header' => 'left', 'heading' => 'accent', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'firasans', 'accent' => '#1D4ED8', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 25, 'summary' => 'plain',
        'gutter' => 38,
        'variants' => ['left', 'split'],
    ]),
    $t(['slug' => 'ca-technical', 'name' => 'Canada Technical', 'country' => 'CA', 'category' => 'technical', 'recommended' => false,
        'description' => 'A side column for technical skills, languages and certifications; the main column for experience and projects.'], [
        'layout' => 'sidebar', 'heading' => 'caps-rule', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'ptsans', 'heading_font' => 'plexmono', 'accent' => '#334155', 'font_size' => 10, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'sidebar' => ['width' => 60, 'style' => 'light'],
        'section_order' => ['summary', 'skills', 'experience', 'projects', 'education', 'certifications', 'languages'],
        'variants' => ['left', 'right'],
    ]),
    $t(['slug' => 'ca-academic', 'name' => 'Canada Academic', 'country' => 'CA', 'category' => 'academic', 'recommended' => false,
        'description' => 'Education and research first: degrees, publications, awards and teaching, with dates in a margin column.'], [
        'layout' => 'single', 'header' => 'centered', 'heading' => 'caps-plain', 'entry' => 'dates-left', 'skills' => 'columns',
        'font' => 'ptserif', 'heading_font' => 'ptserif', 'accent' => '#1F3A5F', 'font_size' => 10.5, 'line_height' => 1.35, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 21, 'summary' => 'plain',
        'section_order' => ['summary', 'education', 'publications', 'awards', 'experience', 'skills', 'languages'],
        'variants' => ['centered', 'left'],
    ]),
    $t(['slug' => 'ca-career-change', 'name' => 'Canada Career Change', 'country' => 'CA', 'category' => 'career', 'recommended' => false,
        'description' => 'Transferable skills come first, followed by relevant experience: a clear story for a new field.'], [
        'layout' => 'single', 'header' => 'left', 'heading' => 'band', 'entry' => 'stacked', 'skills' => 'columns',
        'font' => 'firasans', 'accent' => '#0F766E', 'font_size' => 10.5, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 23, 'summary' => 'boxed',
        'skills_title' => 'transferable',
        'section_order' => ['summary', 'skills', 'experience', 'education', 'certifications', 'volunteer', 'languages'],
        'variants' => ['left', 'centered'],
    ]),
    $t(['slug' => 'ca-maple', 'name' => 'Canada Maple', 'country' => 'CA', 'category' => 'modern', 'recommended' => false,
        'description' => 'A maple-red header band, marker headings and a timeline of roles: warm, confident and bilingual-ready.'], [
        'layout' => 'single', 'header' => 'band', 'heading' => 'marker', 'entry' => 'timeline', 'skills' => 'columns',
        'font' => 'lato', 'accent' => '#9F1239', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 25, 'summary' => 'plain',
        'variants' => ['band', 'left'],
    ]),
    $t(['slug' => 'ca-aurora', 'name' => 'Canada Aurora', 'country' => 'CA', 'category' => 'creative', 'recommended' => false,
        'description' => 'A deep teal side column with skills and languages beside a spacious main column with accent headings.'], [
        'layout' => 'sidebar', 'heading' => 'accent', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'firasans', 'accent' => '#0F766E', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 25, 'summary' => 'plain',
        'sidebar' => ['width' => 62, 'style' => 'dark'],
        'variants' => ['left', 'right'],
    ]),
    $t(['slug' => 'ca-summit', 'name' => 'Canada Summit', 'country' => 'CA', 'category' => 'compact', 'recommended' => false,
        'description' => 'A tinted header card and section titles in a slim left column: dense, orderly and elegant for experienced profiles.'], [
        'layout' => 'gutter', 'header' => 'tinted', 'heading' => 'caps-plain', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'ptsans', 'accent' => '#334155', 'font_size' => 10, 'line_height' => 1.35, 'margins' => 'narrow', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 23, 'summary' => 'plain',
        'gutter' => 38,
        'variants' => ['tinted', 'left'],
    ]),

    // ───────────── China ─────────────
    $t(['slug' => 'cn-classic', 'name' => 'China Classic', 'country' => 'CN', 'category' => 'classic', 'recommended' => true,
        'description' => '简洁正式：姓名与基本信息在左，照片在右，标题清晰。A formal, readable Simplified Chinese layout.'], [
        'layout' => 'single', 'header' => 'photo-right', 'heading' => 'band', 'entry' => 'stacked', 'skills' => 'grouped',
        'font' => 'lato', 'accent' => '#1F3A5F', 'font_size' => 10.5, 'line_height' => 1.45, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'variants' => ['photo-right', 'centered'],
    ]),
    $t(['slug' => 'cn-modern', 'name' => 'China Modern', 'country' => 'CN', 'category' => 'modern', 'recommended' => false,
        'description' => '现代设计：深色侧栏放置照片、联系方式与技能。A contemporary design with a dark side column.'], [
        'layout' => 'sidebar', 'heading' => 'bar', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'firasans', 'accent' => '#1D4ED8', 'font_size' => 10, 'line_height' => 1.45, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'circle', 'name_case' => 'normal', 'name_size' => 24, 'summary' => 'plain',
        'sidebar' => ['width' => 62, 'style' => 'accent'],
        'variants' => ['left', 'right'],
    ]),
    $t(['slug' => 'cn-academic', 'name' => 'China Academic', 'country' => 'CN', 'category' => 'academic', 'recommended' => false,
        'description' => '学术型：教育背景、科研成果与获奖情况优先。Education, research and awards first.'], [
        'layout' => 'single', 'header' => 'centered', 'heading' => 'caps-rule', 'entry' => 'dates-left', 'skills' => 'columns',
        'font' => 'plexserif', 'heading_font' => 'plexserif', 'accent' => '#9F1239', 'font_size' => 10.5, 'line_height' => 1.4, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 21, 'summary' => 'plain',
        'section_order' => ['summary', 'education', 'publications', 'awards', 'projects', 'experience', 'skills', 'languages'],
        'variants' => ['centered', 'left'],
    ]),
    $t(['slug' => 'cn-bilingual', 'name' => 'China Bilingual', 'country' => 'CN', 'category' => 'bilingual', 'recommended' => true,
        'description' => '中英双语标题，适合外企与国际岗位。Section titles in Chinese and English for international employers.'], [
        'layout' => 'single', 'header' => 'split', 'heading' => 'bilingual', 'entry' => 'stacked', 'skills' => 'grouped',
        'font' => 'lato', 'accent' => '#0F766E', 'font_size' => 10.5, 'line_height' => 1.45, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'bilingual' => ['zh' => 'en', 'en' => 'zh'],
        'languages' => ['zh', 'en'],
        'variants' => ['split', 'centered'],
    ]),
    $t(['slug' => 'cn-technical', 'name' => 'China Technical', 'country' => 'CN', 'category' => 'technical', 'recommended' => false,
        'description' => '技术岗位：技能与项目经历优先，标题位于左栏。Skills and projects first, titles in a left column.'], [
        'layout' => 'gutter', 'header' => 'left', 'heading' => 'accent', 'entry' => 'stacked', 'skills' => 'grouped',
        'font' => 'ptsans', 'heading_font' => 'plexmono', 'accent' => '#334155', 'font_size' => 10, 'line_height' => 1.4, 'margins' => 'narrow', 'spacing' => 'normal',
        'photo' => false, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 22, 'summary' => 'plain',
        'gutter' => 30,
        'section_order' => ['summary', 'skills', 'projects', 'experience', 'education', 'awards', 'languages'],
        'variants' => ['left', 'split'],
    ]),
    $t(['slug' => 'cn-elite', 'name' => 'China Elite', 'country' => 'CN', 'category' => 'executive', 'recommended' => false,
        'description' => '精英版：浅色标题卡片配照片，实心标题栏。A tinted header card with photo and solid section bands.'], [
        'layout' => 'single', 'header' => 'tinted', 'heading' => 'solid', 'entry' => 'stacked', 'skills' => 'grouped',
        'font' => 'lato', 'accent' => '#9F1239', 'font_size' => 10, 'line_height' => 1.45, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 25, 'summary' => 'plain',
        'variants' => ['tinted', 'photo-right'],
    ]),
    $t(['slug' => 'cn-jade', 'name' => 'China Jade', 'country' => 'CN', 'category' => 'creative', 'recommended' => false,
        'description' => '翡翠版：深绿色侧栏放置照片、联系方式与技能。A deep jade side column with photo, contact details and skills.'], [
        'layout' => 'sidebar', 'heading' => 'marker', 'entry' => 'stacked', 'skills' => 'inline',
        'font' => 'lato', 'accent' => '#047857', 'font_size' => 10, 'line_height' => 1.45, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'square', 'name_case' => 'normal', 'name_size' => 25, 'summary' => 'plain',
        'sidebar' => ['width' => 62, 'style' => 'dark'],
        'variants' => ['left', 'right'],
    ]),
    $t(['slug' => 'cn-ink', 'name' => 'China Ink', 'country' => 'CN', 'category' => 'elegant', 'recommended' => false,
        'description' => '水墨版：墨色时间轴与居中标题，简洁典雅。Ink-black timeline and a centred header: calm and elegant.'], [
        'layout' => 'single', 'header' => 'centered', 'heading' => 'caps-rule', 'entry' => 'timeline', 'skills' => 'grouped',
        'font' => 'plexserif', 'heading_font' => 'plexserif', 'accent' => '#111827', 'font_size' => 10, 'line_height' => 1.45, 'margins' => 'normal', 'spacing' => 'normal',
        'photo' => true, 'photo_shape' => 'circle', 'name_case' => 'normal', 'name_size' => 24, 'summary' => 'plain',
        'variants' => ['centered', 'left'],
    ]),
];
