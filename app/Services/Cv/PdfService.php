<?php

declare(strict_types=1);

namespace App\Services\Cv;

use App\Core\Storage;
use App\Services\Countries;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * Server-side A4 PDF generation with mPDF (chosen over Dompdf for its Arabic shaping, right-to-
 * left layout and CJK font fallback). The document is rendered from the same HTML as the preview,
 * through a dedicated PDF path: real A4 pages (210 × 297 mm), margins from the design settings,
 * automatic extra pages, no screenshots.
 */
final class PdfService
{
    /**
     * @param array<string, mixed> $cv
     * @param array<string, mixed> $template
     * @param array<string, string|null> $photo  pdf / pdf_circle file paths
     * @return array{bytes: string, pages: int}
     */
    public function generate(array $cv, array $template, array $photo = []): array
    {
        $renderer = new CvRenderer();
        $doc = $renderer->render($cv, $template, 'pdf', $photo);
        $page = $doc['page'];

        $mpdf = $this->make($page, $doc['dir'], (string) $cv['language_code']);
        $strip = $page['sidebar'] ? $this->stripImage($page, (string) $cv['design']['accent']) : null;

        $title = trim((string) ($cv['content']['personal']['fullName'] ?? '')) ?: 'CV';
        $mpdf->SetTitle($title . ' — CV');
        $mpdf->SetAuthor($title);
        $mpdf->SetCreator((string) config('app.name', 'ResumeAtlas'));

        $css = $renderer->pdfPageCss($page, $strip) . $doc['css'];
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        // The body is written without a wrapper: mPDF positions absolute elements (the sidebar)
        // relative to the page only when they are top-level.
        $mpdf->WriteHTML($doc['body'], \Mpdf\HTMLParserMode::HTML_BODY);

        $bytes = $mpdf->Output('', 'S');
        return ['bytes' => $bytes, 'pages' => count($mpdf->pages)];
    }

    /** @param array<string, mixed> $page */
    private function make(array $page, string $dir, string $lang): Mpdf
    {
        $defaults = (new ConfigVariables())->getDefaults();
        $fontDefaults = (new FontVariables())->getDefaults();
        $fontdata = $fontDefaults['fontdata'];
        foreach (config('design.fonts', []) as $key => $font) {
            $files = $font['files'];
            $files['I'] ??= $files['R'];
            $files['BI'] ??= $files['B'] ?? $files['R'];
            $fontdata[$key] = $files;
        }
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            // mPDF mirrors left/right margins for right-to-left documents.
            'margin_left' => $dir === 'rtl' ? $page['right'] : $page['left'],
            'margin_right' => $dir === 'rtl' ? $page['left'] : $page['right'],
            'margin_top' => $page['top'],
            'margin_bottom' => $page['bottom'],
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => Storage::path('cache/mpdf'),
            'fontDir' => array_merge($defaults['fontDir'], [BASE_PATH . '/public/assets/fonts']),
            'fontdata' => $fontdata,
            'default_font' => 'lato',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
            'use_kwt' => false,
            'shrink_tables_to_fit' => 1,
            'ignore_invalid_utf8' => true,
            'img_dpi' => 150,
        ]);
        if ($dir === 'rtl') {
            $mpdf->SetDirectionality('rtl');
        }
        unset($lang);
        return $mpdf;
    }

    /**
     * Full-page PNG painting the sidebar strip on every page (mPDF @page background).
     * @param array<string, mixed> $page
     */
    private function stripImage(array $page, string $accent): string
    {
        $sb = $page['sidebar'];
        $color = Design::sidebarColor((string) $sb['style'], $accent);
        $key = substr(sha1($color . '|' . $sb['width'] . '|' . $sb['side']), 0, 16);
        $file = Storage::path('cache/strips') . '/' . $key . '.png';
        if (!is_file($file)) {
            $scale = 4; // px per mm
            $w = (int) (210 * $scale);
            $h = (int) (297 * $scale);
            $im = imagecreatetruecolor($w, $h);
            imagefill($im, 0, 0, (int) imagecolorallocate($im, 255, 255, 255));
            [$r, $g, $b] = array_map('hexdec', str_split(ltrim($color, '#'), 2));
            $fill = (int) imagecolorallocate($im, $r, $g, $b);
            $sw = (int) round($sb['width'] * $scale);
            if ($sb['side'] === 'left') {
                imagefilledrectangle($im, 0, 0, $sw - 1, $h, $fill);
            } else {
                imagefilledrectangle($im, $w - $sw, 0, $w, $h, $fill);
            }
            imagepng($im, $file . '.tmp', 6);
            imagedestroy($im);
            rename($file . '.tmp', $file);
        }
        return $file;
    }

    /** "Camille_Laurent_CV_France.pdf" — Unicode letters kept, everything else collapsed to "_". */
    public static function filename(array $cv): string
    {
        $name = trim((string) ($cv['content']['personal']['fullName'] ?? ''));
        $name = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '_', $name), '_');
        $country = (string) preg_replace('/[^A-Za-z]+/', '_', Countries::name((string) $cv['country_code']));
        $parts = array_filter([$name !== '' ? mb_substr($name, 0, 60) : 'My', 'CV', trim($country, '_')]);
        return implode('_', $parts) . '.pdf';
    }
}
