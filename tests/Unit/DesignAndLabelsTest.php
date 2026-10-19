<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cv\Design;
use App\Services\Labels;
use Tests\TestCase;

final class DesignAndLabelsTest extends TestCase
{
    public function testDesignDefaultsComeFromTemplateAndCountry(): void
    {
        $us = Design::defaults($this->template('us-ats-classic'), 'US');
        $this->assertFalse($us['showPhoto'], 'US templates do not show a photo by default');
        $fr = Design::defaults($this->template('fr-classic'), 'FR');
        $this->assertTrue($fr['showPhoto']);
        $this->assertSame('month_year', $fr['dateStyle']);
    }

    public function testDesignRejectsUnreadableOrInvalidValues(): void
    {
        $t = $this->template('fr-classic');
        $d = Design::sanitize([
            'accent' => '#FFFF00', // too light on white
            'font' => 'comic-sans',
            'fontSize' => 40,
            'lineHeight' => 0.5,
            'margins' => 'none',
            'variant' => 'upside-down',
            'extra' => 'ignored',
        ], $t, 'FR');
        $defaults = Design::defaults($t, 'FR');
        $this->assertSame($defaults['accent'], $d['accent']);
        $this->assertSame($defaults['font'], $d['font']);
        $this->assertSame(12.0, $d['fontSize']);
        $this->assertSame(1.15, $d['lineHeight']);
        $this->assertSame($defaults['margins'], $d['margins']);
        $this->assertSame($defaults['variant'], $d['variant']);
        $this->assertArrayNotHasKey('extra', $d);
    }

    public function testDesignAcceptsValidValues(): void
    {
        $d = Design::sanitize(['accent' => '#0f766e', 'font' => 'ptserif', 'fontSize' => 9.7, 'margins' => 'wide', 'variant' => 'left'], $this->template('fr-classic'), 'FR');
        $this->assertSame('#0F766E', $d['accent']);
        $this->assertSame('ptserif', $d['font']);
        $this->assertSame(9.5, $d['fontSize']);
        $this->assertSame('wide', $d['margins']);
        $this->assertSame('left', $d['variant']);
    }

    public function testPaletteIsReadableOnWhite(): void
    {
        $this->assertEqualsWithDelta(21.0, Design::contrastWithWhite('#000000'), 0.01);
        $this->assertEqualsWithDelta(1.0, Design::contrastWithWhite('#FFFFFF'), 0.01);
        foreach (array_keys(config('design.palette')) as $hex) {
            $this->assertGreaterThanOrEqual(4.5, Design::contrastWithWhite($hex), "$hex is readable on white");
        }
    }

    public function testDatesFollowLanguageConventions(): void
    {
        $this->assertSame('Mar 2024', Labels::date('en', '2024-03', 'month_year'));
        $this->assertSame('03/2024', Labels::date('en', '2024-03', 'numeric'));
        $this->assertSame('2024', Labels::date('fr', '2024-03', 'year'));
        $this->assertStringContainsString('2024', Labels::date('fr', '2024-03', 'month_year'));
        $this->assertSame('2024年3月', Labels::date('zh', '2024-03', 'month_year'));
        $this->assertSame('2024.03', Labels::date('zh', '2024-03', 'numeric'));
        $this->assertSame('rtl', Labels::dir('ar'));
        $this->assertSame('ltr', Labels::dir('zh'));
    }

    public function testEveryLanguageHasEveryLabel(): void
    {
        $en = Labels::for('en');
        foreach (['fr', 'ar', 'zh'] as $lang) {
            $l = Labels::for($lang);
            foreach (['sections', 'labels', 'levels', 'employment_types', 'date_formats'] as $group) {
                $this->assertSame([], array_values(array_diff(array_keys($en[$group]), array_keys($l[$group]))), "$lang.$group is complete");
            }
            $this->assertCount(12, $l['months']);
        }
    }
}
