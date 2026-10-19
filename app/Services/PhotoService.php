<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Storage;

/**
 * Profile photos. Uploads are checked (size, real image type via finfo and GD), re-encoded
 * (which strips metadata and anything that is not pixels), cropped to a square and stored under
 * private storage with a random name. A circular PNG variant is made for round-photo templates.
 * Files are served only through an authenticated, ownership-checked route.
 */
final class PhotoService
{
    private const DIR = 'uploads/photos';
    private const SIZE = 600;
    private const MIMES = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * @param array<string, mixed> $file an entry of $_FILES
     * @return string the stored photo id (file name without extension)
     */
    public static function store(array $file, bool $requireUploaded = true): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new HttpException(422, 'The photo is too large (maximum 2 MB).', ['photo' => 'too large'], 'photo_too_large');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'The photo could not be uploaded. Please try again.', ['photo' => 'upload error'], 'photo_upload');
        }
        $tmp = (string) $file['tmp_name'];
        if ($requireUploaded && !is_uploaded_file($tmp)) {
            throw new HttpException(400, 'Invalid upload.');
        }
        $size = (int) filesize($tmp);
        if ($size <= 0 || $size > (int) config('app.limits.photo_max_bytes', 2097152)) {
            throw new HttpException(422, 'The photo is too large (maximum 2 MB).', ['photo' => 'too large'], 'photo_too_large');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::MIMES[$mime])) {
            throw new HttpException(422, 'Please upload a JPEG, PNG or WebP image.', ['photo' => 'type'], 'photo_type');
        }
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 64 || $info[1] < 64 || $info[0] > 8000 || $info[1] > 8000) {
            throw new HttpException(422, 'This image cannot be used (it must be between 64 and 8000 pixels wide).', ['photo' => 'dimensions'], 'photo_dimensions');
        }
        $src = match (self::MIMES[$mime]) {
            'jpeg' => @imagecreatefromjpeg($tmp),
            'png' => @imagecreatefrompng($tmp),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        };
        if (!$src) {
            throw new HttpException(422, 'This image could not be read. Try another file.', ['photo' => 'unreadable'], 'photo_unreadable');
        }
        $src = self::orient($src, $tmp, $mime);
        $square = self::squareCrop($src, self::SIZE);
        imagedestroy($src);

        $id = bin2hex(random_bytes(16));
        $dir = Storage::path(self::DIR);
        imagejpeg($square, $dir . '/' . $id . '.jpg', 86);
        $circle = self::circle($square, 400);
        imagepng($circle, $dir . '/' . $id . '-circle.png', 7);
        imagedestroy($square);
        imagedestroy($circle);
        return $id;
    }

    public static function path(string $id, string $shape = 'square'): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }
        $file = Storage::path(self::DIR) . '/' . $id . ($shape === 'circle' ? '-circle.png' : '.jpg');
        return is_file($file) ? $file : null;
    }

    /** Copies a photo for a duplicated CV, so each CV owns its file. */
    public static function copy(string $id): ?string
    {
        $square = self::path($id);
        $circle = self::path($id, 'circle');
        if ($square === null) {
            return null;
        }
        $new = bin2hex(random_bytes(16));
        $dir = Storage::path(self::DIR);
        copy($square, $dir . '/' . $new . '.jpg');
        if ($circle) {
            copy($circle, $dir . '/' . $new . '-circle.png');
        }
        return $new;
    }

    public static function delete(?string $id): void
    {
        if ($id === null || !preg_match('/^[a-f0-9]{32}$/', $id)) {
            return;
        }
        foreach (['square', 'circle'] as $shape) {
            $f = self::path($id, $shape);
            if ($f) {
                @unlink($f);
            }
        }
    }

    /** Applies the EXIF orientation of phone pictures. */
    private static function orient(\GdImage $img, string $file, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($file);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($angle !== 0) {
            $rotated = imagerotate($img, $angle, 0);
            if ($rotated) {
                imagedestroy($img);
                return $rotated;
            }
        }
        return $img;
    }

    private static function squareCrop(\GdImage $src, int $size): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $x = (int) (($w - $side) / 2);
        // Keep a little more of the top for portraits.
        $y = $h > $w ? (int) (($h - $side) * 0.3) : (int) (($h - $side) / 2);
        $dst = imagecreatetruecolor($size, $size);
        imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, $x, $y, $size, $size, $side, $side);
        return $dst;
    }

    /** Anti-aliased circular cut-out with a transparent background. */
    private static function circle(\GdImage $square, int $size): \GdImage
    {
        $src = imagecreatetruecolor($size, $size);
        imagecopyresampled($src, $square, 0, 0, 0, 0, $size, $size, imagesx($square), imagesy($square));
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = (int) imagecolorallocatealpha($out, 255, 255, 255, 127);
        imagefill($out, 0, 0, $transparent);
        $r = $size / 2;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $d = sqrt(($x + 0.5 - $r) ** 2 + ($y + 0.5 - $r) ** 2);
                if ($d > $r) {
                    continue;
                }
                $rgb = imagecolorat($src, $x, $y);
                $alpha = $d > $r - 1 ? (int) round(127 * ($d - ($r - 1))) : 0;
                imagesetpixel($out, $x, $y, (int) imagecolorallocatealpha($out, ($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255, $alpha));
            }
        }
        imagedestroy($src);
        return $out;
    }
}
