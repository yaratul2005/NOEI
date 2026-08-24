<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pure-PHP Image Resizing & Responsive Thumbnail Generation Engine for NOEI CMS.
 * Uses GD library with memory-efficient handling suitable for shared hosting.
 */
class ImageService
{
    /**
     * Standard responsive size definitions.
     */
    public const SIZES = [
        'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
        'medium'    => ['width' => 300, 'height' => 300, 'crop' => false],
        'large'     => ['width' => 1024, 'height' => 1024, 'crop' => false],
    ];

    /**
     * Check if GD extension is loaded and supports image processing.
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /**
     * Generate responsive image variants (thumbnail, medium, large).
     *
     * @param string $sourcePath Full absolute path to source image
     * @param string $mimeType File MIME type
     * @return array<string, array{path: string, width: int, height: int}>
     */
    public function generateVariants(string $sourcePath, string $mimeType): array
    {
        $supportedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (!in_array($mimeType, $supportedMimes, true) || !file_exists($sourcePath)) {
            return [];
        }

        $imageInfo = @getimagesize($sourcePath);
        $origWidth = $imageInfo[0] ?? 100;
        $origHeight = $imageInfo[1] ?? 100;

        $pathInfo = pathinfo($sourcePath);
        $dirname = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = strtolower($pathInfo['extension'] ?? 'jpg');

        $variants = [];

        // If GD is unavailable, create file copy variants as graceful fallback
        if (!self::isAvailable()) {
            foreach (self::SIZES as $sizeName => $config) {
                $variantFilename = "{$filename}-{$sizeName}.{$extension}";
                $variantPath = "{$dirname}/{$variantFilename}";
                @copy($sourcePath, $variantPath);
                $variants[$sizeName] = [
                    'filename' => $variantFilename,
                    'path' => str_replace('\\', '/', $variantPath),
                    'width' => $origWidth,
                    'height' => $origHeight,
                ];
            }
            return $variants;
        }

        foreach (self::SIZES as $sizeName => $config) {
            $targetWidth = $config['width'];
            $targetHeight = $config['height'];
            $crop = $config['crop'];

            $variantFilename = "{$filename}-{$sizeName}.{$extension}";
            $variantPath = "{$dirname}/{$variantFilename}";

            if ($this->resizeImage($sourcePath, $variantPath, $mimeType, $origWidth, $origHeight, $targetWidth, $targetHeight, $crop)) {
                $varInfo = @getimagesize($variantPath);
                $variants[$sizeName] = [
                    'filename' => $variantFilename,
                    'path' => str_replace('\\', '/', $variantPath),
                    'width' => $varInfo[0] ?? $targetWidth,
                    'height' => $varInfo[1] ?? $targetHeight,
                ];
            }
        }

        return $variants;
    }

    /**
     * Perform GD image resizing with proportional scaling or square cropping.
     *
     * @param string $srcPath
     * @param string $destPath
     * @param string $mimeType
     * @param int $origW
     * @param int $origH
     * @param int $targetW
     * @param int $targetH
     * @param bool $crop
     * @return bool
     */
    private function resizeImage(
        string $srcPath,
        string $destPath,
        string $mimeType,
        int $origW,
        int $origH,
        int $targetW,
        int $targetH,
        bool $crop
    ): bool {
        $srcImage = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($srcPath),
            'image/png' => @imagecreatefrompng($srcPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false,
            'image/gif' => @imagecreatefromgif($srcPath),
            default => false,
        };

        if (!$srcImage) {
            return false;
        }

        $srcX = 0;
        $srcY = 0;
        $srcW = $origW;
        $srcH = $origH;

        if ($crop) {
            $destW = $targetW;
            $destH = $targetH;

            $aspectOrig = $origW / $origH;
            $aspectTarget = $targetW / $targetH;

            if ($aspectOrig >= $aspectTarget) {
                $srcW = (int)round($origH * $aspectTarget);
                $srcX = (int)round(($origW - $srcW) / 2);
            } else {
                $srcH = (int)round($origW / $aspectTarget);
                $srcY = (int)round(($origH - $srcH) / 2);
            }
        } else {
            // Proportional scaling
            $ratio = min($targetW / max(1, $origW), $targetH / max(1, $origH));
            if ($ratio > 1) {
                $ratio = 1; // Keep original scale if image is smaller
            }
            $destW = max(1, (int)round($origW * $ratio));
            $destH = max(1, (int)round($origH * $ratio));
        }

        $destImage = imagecreatetruecolor($destW, $destH);
        if (!$destImage) {
            imagedestroy($srcImage);
            return false;
        }

        // Preserve transparency for PNG, WebP, and GIF
        if (in_array($mimeType, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            if ($transparent !== false) {
                imagefilledrectangle($destImage, 0, 0, $destW, $destH, $transparent);
            }
        }

        imagecopyresampled($destImage, $srcImage, 0, 0, $srcX, $srcY, $destW, $destH, $srcW, $srcH);

        $saved = match ($mimeType) {
            'image/jpeg' => imagejpeg($destImage, $destPath, 85),
            'image/png' => imagepng($destImage, $destPath, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($destImage, $destPath, 80) : false,
            'image/gif' => imagegif($destImage, $destPath),
            default => false,
        };

        imagedestroy($srcImage);
        imagedestroy($destImage);

        return $saved;
    }
}
