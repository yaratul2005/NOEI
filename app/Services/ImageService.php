<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Pure-PHP Image Resizing & WebP Optimization Engine for NOEI CMS.
 * Uses GD library with memory-efficient handling and automatic WebP generation suitable for shared hosting.
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
     * Check if WebP generation is supported by the current GD environment.
     *
     * @return bool
     */
    public static function supportsWebp(): bool
    {
        return self::isAvailable() && function_exists('imagewebp');
    }

    /**
     * Convert an image file to modern WebP format.
     *
     * @param string $sourcePath
     * @param string $destinationPath
     * @param int $quality (0-100)
     * @return bool
     */
    public function convertToWebp(string $sourcePath, string $destinationPath, int $quality = 80): bool
    {
        if (!self::supportsWebp() || !file_exists($sourcePath)) {
            return false;
        }

        $imageInfo = @getimagesize($sourcePath);
        $mime = $imageInfo['mime'] ?? '';

        $srcImage = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (!$srcImage) {
            return false;
        }

        // Preserve alpha transparency
        imagealphablending($srcImage, false);
        imagesavealpha($srcImage, true);

        $saved = @imagewebp($srcImage, $destinationPath, $quality);
        imagedestroy($srcImage);

        return (bool)$saved;
    }

    /**
     * Generate responsive image variants (thumbnail, medium, large) and modern WebP copies.
     *
     * @param string $sourcePath Full absolute path to source image
     * @param string $mimeType File MIME type
     * @return array<string, array{filename: string, path: string, width: int, height: int, webp_path?: string}>
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

        // Generate full-size WebP if source is not already WebP
        if (self::supportsWebp() && $mimeType !== 'image/webp') {
            $origWebpFilename = "{$filename}.webp";
            $origWebpPath = "{$dirname}/{$origWebpFilename}";
            if ($this->convertToWebp($sourcePath, $origWebpPath)) {
                $variants['original_webp'] = [
                    'filename' => $origWebpFilename,
                    'path' => str_replace('\\', '/', $origWebpPath),
                    'width' => $origWidth,
                    'height' => $origHeight,
                ];
            }
        }

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
                $varW = $varInfo[0] ?? $targetWidth;
                $varH = $varInfo[1] ?? $targetHeight;

                $varData = [
                    'filename' => $variantFilename,
                    'path' => str_replace('\\', '/', $variantPath),
                    'width' => $varW,
                    'height' => $varH,
                ];

                // Generate modern WebP variant
                if (self::supportsWebp() && $mimeType !== 'image/webp') {
                    $webpFilename = "{$filename}-{$sizeName}.webp";
                    $webpPath = "{$dirname}/{$webpFilename}";
                    if ($this->convertToWebp($variantPath, $webpPath)) {
                        $varData['webp_filename'] = $webpFilename;
                        $varData['webp_path'] = str_replace('\\', '/', $webpPath);
                    }
                }

                $variants[$sizeName] = $varData;
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

        return (bool)$saved;
    }
}
