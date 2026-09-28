<?php

declare(strict_types=1);

namespace Sarva\Services;

/**
 * Convert raster uploads to WebP at the original pixel size (no resize).
 */
final class ImageConverter
{
    /**
     * @return array{ok:bool, path:?string, mime:?string, size:int, width:?int, height:?int, message:?string}
     */
    public function toWebp(string $sourcePath, string $destinationPath, int $quality = 82): array
    {
        if (!is_file($sourcePath)) {
            return $this->fail('فایل منبع یافت نشد.');
        }
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
            return $this->fail('افزونه GD با پشتیبانی WebP روی سرور فعال نیست.');
        }

        $binary = @file_get_contents($sourcePath);
        if ($binary === false || $binary === '') {
            return $this->fail('خواندن فایل تصویر ناموفق بود.');
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return $this->fail('فرمت تصویر پشتیبانی نمی‌شود.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $dir = dirname($destinationPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            unset($image);
            return $this->fail('ساخت پوشه مقصد ناموفق بود.');
        }

        $quality = max(1, min(100, $quality));
        $ok = @imagewebp($image, $destinationPath, $quality);
        unset($image);

        if (!$ok || !is_file($destinationPath)) {
            return $this->fail('تبدیل به WebP ناموفق بود.');
        }

        return [
            'ok' => true,
            'path' => $destinationPath,
            'mime' => 'image/webp',
            'size' => (int) filesize($destinationPath),
            'width' => $width,
            'height' => $height,
            'message' => null,
        ];
    }

    /**
     * @return array{ok:bool, path:?string, mime:?string, size:int, width:?int, height:?int, message:?string}
     */
    private function fail(string $message): array
    {
        return [
            'ok' => false,
            'path' => null,
            'mime' => null,
            'size' => 0,
            'width' => null,
            'height' => null,
            'message' => $message,
        ];
    }
}
