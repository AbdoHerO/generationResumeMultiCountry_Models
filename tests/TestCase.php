<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Template;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (static::needsDatabase() && !TEST_DB_READY) {
            $this->markTestSkipped('MySQL test database is not available.');
        }
    }

    protected static function needsDatabase(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    protected function template(string $slug): array
    {
        $t = Template::findBySlug($slug);
        $this->assertNotNull($t, "Template $slug is seeded");
        return $t;
    }
}
