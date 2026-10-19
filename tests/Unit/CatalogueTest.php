<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Countries;
use App\Services\Cv\CvSchema;
use Tests\TestCase;

/** The country and template catalogue required by the product brief. */
final class CatalogueTest extends TestCase
{
    public function testFiveCountriesWithAtLeastFiveTemplatesEach(): void
    {
        $countries = Countries::all();
        $this->assertSame(['FR', 'MA', 'US', 'CA', 'CN'], array_keys($countries));
        $byCountry = [];
        foreach (config('templates') as $t) {
            $byCountry[$t['country']][] = $t;
        }
        foreach (array_keys($countries) as $code) {
            $this->assertGreaterThanOrEqual(5, count($byCountry[$code] ?? []), "$code has 5 templates");
            $first = $byCountry[$code][0];
            $this->assertSame('single', $first['config']['layout'], "The first $code template is a simple single-column layout");
            $this->assertTrue($first['recommended'], "The first $code template is recommended");
        }
        $this->assertCount(25, array_unique(array_column(config('templates'), 'slug')));
        $this->assertCount(25, array_unique(array_column(config('templates'), 'name')), 'Template names are distinct');
    }

    public function testTemplatesUseKnownSettings(): void
    {
        $fonts = config('design.fonts');
        foreach (config('templates') as $t) {
            $c = $t['config'];
            $this->assertContains($c['layout'], ['single', 'sidebar', 'gutter'], $t['slug']);
            $this->assertArrayHasKey($c['font'], $fonts, $t['slug']);
            $this->assertArrayHasKey($c['heading_font'] ?? $c['font'], $fonts, $t['slug']);
            $this->assertArrayHasKey($c['margins'], config('design.margins'), $t['slug']);
            foreach ($c['section_order'] ?? [] as $type) {
                $this->assertArrayHasKey($type, CvSchema::SECTION_TYPES, $t['slug']);
            }
        }
    }

    public function testFontFilesShipWithTheApp(): void
    {
        foreach (config('design.fonts') as $key => $font) {
            foreach ($font['files'] as $file) {
                $this->assertFileExists(BASE_PATH . '/public/assets/fonts/' . $file, "$key font file");
            }
        }
    }

    public function testCountryConventions(): void
    {
        $this->assertSame('discouraged', Countries::get('US')['photo']);
        $this->assertSame('discouraged', Countries::get('CA')['photo']);
        $this->assertContains('ar', Countries::get('MA')['languages']);
        $this->assertContains('zh', Countries::get('CN')['languages']);
        $this->assertSame('fr', Countries::resolveLanguage('FR', 'zh'), 'An unsupported language falls back to the country default');
    }

    public function testNewCvFollowsTheCountrySectionOrder(): void
    {
        $content = CvSchema::defaultContent('US', 'en', Countries::get('US')['section_order']);
        $this->assertSame(Countries::get('US')['section_order'], array_column($content['sections'], 'type'));
        $this->assertSame('+1', $content['personal']['phoneCode']);
    }
}
