<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\HttpException;
use App\Services\PhotoService;
use Tests\TestCase;

final class PhotoServiceTest extends TestCase
{
    protected static function needsDatabase(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    private function upload(string $path): array
    {
        return ['tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'name' => basename($path)];
    }

    public function testStoresAReEncodedSquareAndCircleOutsideThePublicFolder(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'img');
        $img = imagecreatetruecolor(300, 200);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 60, 90));
        imagepng($img, $src);
        imagedestroy($img);

        $id = PhotoService::store($this->upload($src), false);
        @unlink($src);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
        $square = PhotoService::path($id);
        $circle = PhotoService::path($id, 'circle');
        $this->assertNotNull($square);
        $this->assertNotNull($circle);
        $this->assertStringNotContainsString(str_replace('\\', '/', BASE_PATH . '/public'), str_replace('\\', '/', (string) $square));
        [$w, $h, $type] = getimagesize((string) $square);
        $this->assertSame([$w, $h, $type], [$w, $w, IMAGETYPE_JPEG], 'Square JPEG');
        $this->assertSame(IMAGETYPE_PNG, getimagesize((string) $circle)[2]);

        $copy = PhotoService::copy($id);
        $this->assertNotNull($copy);
        PhotoService::delete($id);
        PhotoService::delete($copy);
        $this->assertNull(PhotoService::path($id));
    }

    public function testRejectsFilesThatAreNotImages(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($src, "<?php echo 'hi';");
        try {
            PhotoService::store($this->upload($src), false);
            $this->fail('A PHP file was accepted as a photo');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status);
        } finally {
            @unlink($src);
        }
    }

    public function testRejectsPolyglotWithImageExtensionButWrongContent(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'img') . '.jpg';
        file_put_contents($src, "GIF89a<script>alert(1)</script>");
        try {
            PhotoService::store($this->upload($src), false);
            $this->fail('A fake image was accepted');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status);
        } finally {
            @unlink($src);
        }
    }

    public function testPathRejectsTraversal(): void
    {
        $this->assertNull(PhotoService::path('../../.env'));
        $this->assertNull(PhotoService::path('abc'));
    }

    public function testRequiresARealUploadByDefault(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($src, 'x');
        $this->expectException(HttpException::class);
        try {
            PhotoService::store($this->upload($src));
        } finally {
            @unlink($src);
        }
    }
}
