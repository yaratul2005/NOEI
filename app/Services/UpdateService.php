<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use RuntimeException;
use ZipArchive;

/**
 * One-Click CMS Updater, Pre-Update Snapshot, and Rollback Service for NOEI CMS.
 */
class UpdateService
{
    public const CURRENT_VERSION = '1.0.0-alpha';

    private string $rootDir;
    private string $cacheDir;
    private string $backupDir;
    private BackupService $backupService;

    public function __construct(?string $rootDir = null, ?BackupService $backupService = null)
    {
        $this->rootDir = rtrim($rootDir ?? dirname(__DIR__, 2), '/\\');
        $this->cacheDir = "{$this->rootDir}/storage/cache";
        $this->backupDir = "{$this->rootDir}/storage/backups";
        $this->backupService = $backupService ?? new BackupService($this->backupDir, $this->rootDir);

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Get current installed CMS version.
     *
     * @return string
     */
    public function getCurrentVersion(): string
    {
        return self::CURRENT_VERSION;
    }

    /**
     * Check for new CMS releases.
     *
     * @param string|null $endpoint
     * @return array{has_update: bool, current_version: string, latest_version: string, release_notes: string, download_url: string, checksum: string}
     */
    public function checkForUpdates(?string $endpoint = null): array
    {
        $current = $this->getCurrentVersion();

        // If custom endpoint provided, fetch from remote; otherwise provide mock release data
        if ($endpoint && filter_var($endpoint, FILTER_VALIDATE_URL)) {
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $json = @file_get_contents($endpoint, false, $context);
            if ($json) {
                $data = json_decode($json, true) ?: [];
                $latest = $data['version'] ?? $current;
                return [
                    'has_update' => version_compare($latest, $current, '>'),
                    'current_version' => $current,
                    'latest_version' => $latest,
                    'release_notes' => $data['notes'] ?? 'Latest stability and security updates.',
                    'download_url' => $data['download_url'] ?? '',
                    'checksum' => $data['checksum'] ?? '',
                ];
            }
        }

        // Standard response when already on the latest version or offline
        return [
            'has_update' => false,
            'current_version' => $current,
            'latest_version' => $current,
            'release_notes' => 'You are running the latest version of NOEI CMS.',
            'download_url' => '',
            'checksum' => '',
        ];
    }

    /**
     * Create an automated pre-update safety snapshot (database dump + core files).
     *
     * @return string Path to pre-update snapshot ZIP file
     */
    public function createPreUpdateSnapshot(): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("ZipArchive is required to create safety update snapshots.");
        }

        // 1. Create database dump
        $sqlPath = $this->backupService->createDatabaseBackup();

        $ver = preg_replace('/[^a-zA-Z0-9.-]/', '', $this->getCurrentVersion());
        $timestamp = date('Y-m-d-His');
        $zipFilename = "pre-update-v{$ver}-{$timestamp}.zip";
        $zipPath = "{$this->backupDir}/{$zipFilename}";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create pre-update snapshot ZIP file.");
        }

        // Add SQL dump
        $zip->addFile($sqlPath, basename($sqlPath));

        // Add core folders
        $coreFolders = ['app', 'config', 'core', 'themes/default'];
        foreach ($coreFolders as $folder) {
            $fullFolderPath = "{$this->rootDir}/{$folder}";
            if (is_dir($fullFolderPath)) {
                $this->addFolderToZip($fullFolderPath, $folder, $zip);
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Apply update package from local ZIP path.
     *
     * @param string $zipFilePath
     * @return bool
     */
    public function applyUpdatePackage(string $zipFilePath): bool
    {
        if (!file_exists($zipFilePath) || !class_exists('ZipArchive')) {
            return false;
        }

        // 1. Generate pre-update safety snapshot first
        $this->createPreUpdateSnapshot();

        // 2. Open and extract update archive
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            return false;
        }

        // Extract files safely
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Skip dangerous/preserved paths
            if (
                str_starts_with($filename, 'config/database.php') ||
                str_starts_with($filename, 'storage/uploads/') ||
                str_starts_with($filename, 'storage/backups/')
            ) {
                continue;
            }

            $targetPath = "{$this->rootDir}/{$filename}";
            if (str_ends_with($filename, '/')) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                file_put_contents($targetPath, $zip->getFromIndex($i));
            }
        }

        $zip->close();

        // 3. Clear file caches
        $this->clearCache();

        return true;
    }

    /**
     * Roll back system to a pre-update snapshot archive.
     *
     * @param string $snapshotFilename
     * @return bool
     */
    public function rollbackToSnapshot(string $snapshotFilename): bool
    {
        $safeName = basename($snapshotFilename);
        $snapshotPath = "{$this->backupDir}/{$safeName}";

        if (!file_exists($snapshotPath) || !class_exists('ZipArchive')) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($snapshotPath) !== true) {
            return false;
        }

        $sqlFileToRestore = null;

        // Restore files
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if (str_ends_with($filename, '.sql') && !str_contains($filename, '/')) {
                $sqlContent = $zip->getFromIndex($i);
                $tempSqlPath = "{$this->backupDir}/temp_restore.sql";
                file_put_contents($tempSqlPath, $sqlContent);
                $sqlFileToRestore = 'temp_restore.sql';
                continue;
            }

            // Skip preserving database config
            if (str_starts_with($filename, 'config/database.php')) {
                continue;
            }

            $targetPath = "{$this->rootDir}/{$filename}";
            if (str_ends_with($filename, '/')) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                file_put_contents($targetPath, $zip->getFromIndex($i));
            }
        }

        $zip->close();

        // Restore database dump if found in snapshot
        if ($sqlFileToRestore) {
            $this->backupService->restoreDatabase($sqlFileToRestore);
            @unlink("{$this->backupDir}/{$sqlFileToRestore}");
        }

        $this->clearCache();

        return true;
    }

    /**
     * Helper clearing cache files.
     */
    private function clearCache(): void
    {
        $files = glob("{$this->cacheDir}/*.*") ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Helper adding folder to ZipArchive.
     */
    private function addFolderToZip(string $folder, string $zipPath, ZipArchive $zip): void
    {
        $files = scandir($folder) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $realPath = "{$folder}/{$file}";
            $localPath = "{$zipPath}/{$file}";

            if (is_dir($realPath)) {
                $this->addFolderToZip($realPath, $localPath, $zip);
            } else {
                $zip->addFile($realPath, $localPath);
            }
        }
    }
}
