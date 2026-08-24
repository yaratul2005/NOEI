<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;
use Core\Database;
use RuntimeException;

/**
 * Secure File Upload Pipeline and Storage Service for NOEI CMS.
 */
class MediaService
{
    /**
     * Whitelisted file extensions.
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'txt', 'zip'];

    /**
     * Forbidden executable extensions.
     */
    public const FORBIDDEN_EXTENSIONS = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'pl', 'py', 'cgi', 'exe', 'sh', 'bash', 'asp', 'aspx', 'jsp'];

    private Media $mediaModel;
    private ImageService $imageService;
    private string $uploadRootDir;

    public function __construct(?Media $mediaModel = null, ?ImageService $imageService = null, ?string $uploadRootDir = null)
    {
        $this->mediaModel = $mediaModel ?? new Media();
        $this->imageService = $imageService ?? new ImageService();
        $this->uploadRootDir = rtrim($uploadRootDir ?? dirname(__DIR__, 2) . '/storage/uploads', '/\\');
    }

    /**
     * Process an uploaded file safely.
     *
     * @param array $file Structure from $_FILES['file']
     * @param int $userId ID of uploader
     * @return array Created Media record details
     */
    public function upload(array $file, int $userId): array
    {
        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException("No valid file uploaded or upload error code [{$file['error']}].");
        }

        $tmpPath = $file['tmp_name'];
        $originalFilename = $file['name'];

        // Extension check
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true) || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException("File extension [.{$extension}] is not allowed for security reasons.");
        }

        // Real MIME type verification via finfo_file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $tmpPath) ?: 'application/octet-stream';
        finfo_close($finfo);

        // Reject PHP / HTML script MIME types disguised with safe extensions
        if (str_contains($realMime, 'php') || str_contains($realMime, 'javascript') || str_contains($realMime, 'html')) {
            throw new RuntimeException("File content matches dangerous script MIME type [{$realMime}].");
        }

        // Generate Chronological Directory Path (storage/uploads/YYYY/MM)
        $year = date('Y');
        $month = date('m');
        $targetDir = "{$this->uploadRootDir}/{$year}/{$month}";

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Generate unique target filename
        $safeName = bin2hex(random_bytes(12)) . '.' . $extension;
        $targetFullPath = "{$targetDir}/{$safeName}";

        if (is_uploaded_file($tmpPath)) {
            if (!move_uploaded_file($tmpPath, $targetFullPath)) {
                throw new RuntimeException("Failed to save uploaded file to storage directory.");
            }
        } else {
            if (!copy($tmpPath, $targetFullPath)) {
                throw new RuntimeException("Failed to save file to storage directory.");
            }
        }

        // Calculate relative storage URL path
        $relativePath = "storage/uploads/{$year}/{$month}/{$safeName}";

        $metaData = [
            'original_name' => $originalFilename,
            'title' => pathinfo($originalFilename, PATHINFO_FILENAME),
            'alt' => '',
            'variants' => [],
        ];

        // Generate image thumbnails if file is an image
        if (str_starts_with($realMime, 'image/')) {
            $imageInfo = @getimagesize($targetFullPath);
            if ($imageInfo) {
                $metaData['width'] = $imageInfo[0];
                $metaData['height'] = $imageInfo[1];
            }

            $variants = $this->imageService->generateVariants($targetFullPath, $realMime);
            foreach ($variants as $sizeName => $var) {
                $metaData['variants'][$sizeName] = "storage/uploads/{$year}/{$month}/{$var['filename']}";
            }
        }

        // Save DB record
        $mediaId = $this->mediaModel->create([
            'user_id' => $userId,
            'filename' => $originalFilename,
            'file_path' => $relativePath,
            'mime_type' => $realMime,
            'file_size' => filesize($targetFullPath),
            'meta_data' => $metaData,
        ]);

        return array_merge([
            'id' => $mediaId,
            'user_id' => $userId,
            'filename' => $originalFilename,
            'file_path' => $relativePath,
            'mime_type' => $realMime,
            'file_size' => filesize($targetFullPath),
            'meta_data' => $metaData,
        ]);
    }

    /**
     * Delete media record and remove all associated physical file variants.
     *
     * @param int $mediaId
     * @return bool
     */
    public function deleteMedia(int $mediaId): bool
    {
        $media = $this->mediaModel->find($mediaId);
        if (!$media) {
            return false;
        }

        // Delete primary original file
        $primaryPath = dirname(__DIR__, 2) . '/' . ltrim($media['file_path'], '/\\');
        if (file_exists($primaryPath)) {
            @unlink($primaryPath);
        }

        // Delete thumbnail variants if present
        $metaData = json_decode($media['meta_data'] ?? '{}', true) ?: [];
        $variants = $metaData['variants'] ?? [];

        foreach ($variants as $variantRelPath) {
            $varFullPath = dirname(__DIR__, 2) . '/' . ltrim($variantRelPath, '/\\');
            if (file_exists($varFullPath)) {
                @unlink($varFullPath);
            }
        }

        // Delete database record
        return $this->mediaModel->delete($mediaId);
    }
}
