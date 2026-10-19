<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Services\Cv\CvSchema;
use Tests\TestCase;

final class CvSchemaTest extends TestCase
{
    protected static function needsDatabase(): bool
    {
        return false;
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @param array<string, mixed> $personal
     * @return array<string, mixed>
     */
    private function minimal(array $sections = [], array $personal = []): array
    {
        return ['personal' => $personal + ['fullName' => 'Ada Lovelace'], 'sections' => $sections];
    }

    public function testRejectsContentWithoutTheExpectedStructure(): void
    {
        foreach ([null, 'text', [], ['personal' => []], ['personal' => [], 'sections' => ['a' => 1]]] as $bad) {
            try {
                CvSchema::sanitize($bad);
                $this->fail('Accepted invalid content: ' . json_encode($bad));
            } catch (HttpException $e) {
                $this->assertSame(422, $e->status);
            }
        }
    }

    public function testDropsUnknownKeysAndSectionTypes(): void
    {
        $clean = CvSchema::sanitize($this->minimal([
            ['id' => 's1', 'type' => 'summary', 'text' => 'Hello', 'user_id' => 99, 'is_admin' => true],
            ['id' => 's2', 'type' => 'evil', 'text' => 'x'],
        ], ['role' => 'admin', 'user_id' => 1]));
        $this->assertArrayNotHasKey('role', $clean['personal']);
        $this->assertArrayNotHasKey('user_id', $clean['personal']);
        $this->assertCount(1, $clean['sections']);
        $this->assertSame(['id', 'type', 'title', 'hidden', 'text'], array_keys($clean['sections'][0]));
    }

    public function testUniqueSectionTypesAreKeptOnce(): void
    {
        $clean = CvSchema::sanitize($this->minimal([
            ['type' => 'skills', 'items' => []],
            ['type' => 'skills', 'items' => []],
            ['type' => 'custom', 'text' => 'a'],
            ['type' => 'custom', 'text' => 'b'],
        ]));
        $this->assertSame(['skills', 'custom', 'custom'], array_column($clean['sections'], 'type'));
    }

    public function testStringsAreCleanedAndTruncated(): void
    {
        $clean = CvSchema::sanitize($this->minimal([], ['fullName' => "  Ada\x00\x07   Lovelace  " . str_repeat('x', 300), 'headline' => ['array']]));
        $this->assertStringStartsWith('Ada Lovelace', $clean['personal']['fullName']);
        $this->assertSame(120, mb_strlen($clean['personal']['fullName']));
        $this->assertSame('', $clean['personal']['headline']);
    }

    public function testHtmlIsStoredAsTextAndEscapedAtRenderTime(): void
    {
        $clean = CvSchema::sanitize($this->minimal([], ['headline' => '<script>alert(1)</script>']));
        $this->assertSame('<script>alert(1)</script>', $clean['personal']['headline']);
    }

    public function testUrlsAreLimitedToHttp(): void
    {
        $this->assertSame('https://example.com/a', CvSchema::cleanUrl('example.com/a', 300));
        $this->assertSame('http://x.org', CvSchema::cleanUrl('http://x.org', 300));
        $this->assertSame('', CvSchema::cleanUrl('javascript:alert(1)', 300));
        $this->assertSame('', CvSchema::cleanUrl('data:text/html,hi', 300));
        $this->assertSame('', CvSchema::cleanUrl('https://exa mple.com', 300));
    }

    public function testDatesAcceptYearOrYearMonthOnly(): void
    {
        $this->assertSame('2024-03', CvSchema::cleanDate('2024-03'));
        $this->assertSame('2024', CvSchema::cleanDate('2024'));
        $this->assertSame('', CvSchema::cleanDate('2024-13'));
        $this->assertSame('', CvSchema::cleanDate('03/2024'));
        $this->assertSame('', CvSchema::cleanDate('1800'));
    }

    public function testEntriesKeepOnlyTheirFields(): void
    {
        $clean = CvSchema::sanitize($this->minimal([[
            'type' => 'experience',
            'items' => [['title' => 'Dev', 'organization' => 'ACME', 'employmentType' => 'pirate', 'current' => 1, 'bullets' => ['a', ['text' => 'b'], ['x' => 1]], 'salary' => 1]],
        ]]));
        $item = $clean['sections'][0]['items'][0];
        $this->assertArrayNotHasKey('salary', $item);
        $this->assertSame('', $item['employmentType']);
        $this->assertTrue($item['current']);
        $this->assertSame(['a', 'b', ''], $item['bullets']);
        $this->assertMatchesRegularExpression('/^i_[a-f0-9]{10}$/', $item['id']);
    }

    public function testDuplicateIdsAreReplaced(): void
    {
        $clean = CvSchema::sanitize($this->minimal([
            ['id' => 'same', 'type' => 'summary', 'text' => ''],
            ['id' => 'same', 'type' => 'additional', 'text' => ''],
        ]));
        $this->assertNotSame($clean['sections'][0]['id'], $clean['sections'][1]['id']);
    }

    public function testTooManySectionsIsRejected(): void
    {
        $this->expectException(HttpException::class);
        CvSchema::sanitize($this->minimal(array_fill(0, CvSchema::MAX_SECTIONS + 1, ['type' => 'custom'])));
    }

    public function testItemAndBulletLimits(): void
    {
        $clean = CvSchema::sanitize($this->minimal([['type' => 'projects', 'items' => array_fill(0, 80, ['name' => 'P', 'bullets' => array_fill(0, 40, 'b')])]]));
        $this->assertCount(CvSchema::MAX_ITEMS, $clean['sections'][0]['items']);
        $this->assertCount(CvSchema::MAX_BULLETS, $clean['sections'][0]['items'][0]['bullets']);
    }

    public function testStatusAndCompleteness(): void
    {
        $this->assertSame('draft', CvSchema::status($this->minimal()));
        $full = CvSchema::sanitize([
            'personal' => ['fullName' => 'A', 'headline' => 'B', 'email' => 'a@b.co', 'phone' => '1'],
            'sections' => [
                ['type' => 'summary', 'text' => 'Hi'],
                ['type' => 'experience', 'items' => [['title' => 'Dev']]],
                ['type' => 'education', 'items' => [['degree' => 'MSc']]],
                ['type' => 'skills', 'items' => [['name' => 'a'], ['name' => 'b'], ['name' => 'c']]],
                ['type' => 'languages', 'items' => [['name' => 'English']]],
            ],
        ]);
        $this->assertSame('completed', CvSchema::status($full));
        $this->assertSame(100, CvSchema::completeness($full));
    }
}
