<?php

declare(strict_types=1);

namespace Tests\Pdf;

use App\Models\Template;
use App\Services\Cv\CvRenderer;
use App\Services\Cv\CvSchema;
use App\Services\Cv\Design;
use App\Services\Cv\PdfService;
use App\Services\Cv\SampleContent;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * Real PDF output: every template in every language it supports, page size, page count,
 * extractable text, long content and file names.
 */
final class PdfGenerationTest extends TestCase
{
    /** @return array<string, mixed> */
    private function cv(array $template, string $lang, ?array $content = null): array
    {
        $country = (string) $template['country_code'];
        return [
            'id' => 1,
            'content' => $content ?? SampleContent::for($country, $lang),
            'design' => Design::defaults($template, $country),
            'language_code' => $lang,
            'country_code' => $country,
        ];
    }

    public function testEveryTemplateRendersEverySupportedLanguageOnOneA4Page(): void
    {
        $count = 0;
        foreach (Template::all(false) as $t) {
            foreach ($t['languages'] as $lang) {
                $pdf = (new PdfService())->generate($this->cv($t, $lang), $t);
                $this->assertStringStartsWith('%PDF-', $pdf['bytes'], "{$t['slug']}/$lang is a PDF");
                $this->assertSame(1, $pdf['pages'], "{$t['slug']}/$lang sample fits on one page");
                // A4 = 595.28 × 841.89 pt
                $this->assertMatchesRegularExpression('#/MediaBox \[0(\.0+)? 0(\.0+)? 595\.2\d+ 841\.8\d+\]#', $pdf['bytes'], "{$t['slug']}/$lang is A4");
                $count++;
            }
        }
        $this->assertGreaterThanOrEqual(49, $count);
    }

    public function testLatinTextIsExtractable(): void
    {
        $t = $this->template('fr-classic');
        $pdf = (new PdfService())->generate($this->cv($t, 'fr'), $t);
        $text = (new Parser())->parseContent($pdf['bytes'])->getText();
        $this->assertStringContainsString('Camille', $text);
        $this->assertStringContainsStringIgnoringCase('Formation', $text);
        $t = $this->template('us-ats-classic');
        $text = (new Parser())->parseContent((new PdfService())->generate($this->cv($t, 'en'), $t)['bytes'])->getText();
        $this->assertStringContainsStringIgnoringCase('Jordan', $text);
        $this->assertStringContainsString('EXPERIENCE', strtoupper($text));
    }

    public function testArabicAndChineseUseEmbeddedFonts(): void
    {
        foreach ([['ma-essential', 'ar', 'XBRiyaz'], ['cn-classic', 'zh', 'Sun-?ExtA']] as [$slug, $lang, $font]) {
            $t = $this->template($slug);
            $pdf = (new PdfService())->generate($this->cv($t, $lang), $t);
            $this->assertMatchesRegularExpression('/FontName\s*\/[A-Z]{6}\+' . $font . '/i', $pdf['bytes'], "$slug/$lang embeds $font");
        }
    }

    public function testLongContentFlowsOntoMorePages(): void
    {
        $t = $this->template('us-ats-classic');
        $content = SampleContent::for('US', 'en');
        foreach ($content['sections'] as &$s) {
            if ($s['type'] === 'experience') {
                $s['items'] = array_merge(...array_fill(0, 6, $s['items']));
            }
        }
        unset($s);
        $content = CvSchema::sanitize($content);
        $pdf = (new PdfService())->generate($this->cv($t, 'en', $content), $t);
        $this->assertGreaterThanOrEqual(2, $pdf['pages']);

        $t = $this->template('fr-creative'); // sidebar layout
        $content = SampleContent::for('FR', 'fr');
        foreach ($content['sections'] as &$s) {
            if ($s['type'] === 'experience') {
                $s['items'] = array_merge(...array_fill(0, 6, $s['items']));
            }
        }
        unset($s);
        $pdf = (new PdfService())->generate($this->cv($t, 'fr', CvSchema::sanitize($content)), $t);
        $this->assertGreaterThanOrEqual(2, $pdf['pages'], 'Sidebar layout continues on a second page');
    }

    public function testUserTextIsEscapedInTheRenderedHtml(): void
    {
        $t = $this->template('fr-classic');
        $content = SampleContent::for('FR', 'fr');
        $content['personal']['fullName'] = '<img src=x onerror=alert(1)>';
        $html = (new CvRenderer())->render($this->cv($t, 'fr', $content), $t, 'preview')['html'];
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img src=x', $html);
    }

    public function testEmptyCvStillRenders(): void
    {
        $t = $this->template('cn-modern');
        $content = CvSchema::defaultContent('CN', 'zh', ['summary', 'experience']);
        $pdf = (new PdfService())->generate($this->cv($t, 'zh', $content), $t);
        $this->assertSame(1, $pdf['pages']);
    }

    public function testFileNames(): void
    {
        $this->assertSame('Camille_Laurent_CV_France.pdf', PdfService::filename(['content' => ['personal' => ['fullName' => 'Camille Laurent']], 'country_code' => 'FR']));
        $this->assertSame('李明_CV_China.pdf', PdfService::filename(['content' => ['personal' => ['fullName' => '李明']], 'country_code' => 'CN']));
        $this->assertSame('My_CV_United_States.pdf', PdfService::filename(['content' => ['personal' => ['fullName' => '']], 'country_code' => 'US']));
        $this->assertSame('etc_CV_United_States.pdf', PdfService::filename(['content' => ['personal' => ['fullName' => '../../etc']], 'country_code' => 'US']), 'No path characters survive');
    }
}
