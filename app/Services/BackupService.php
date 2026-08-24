<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Pure-PHP Database and Full Site Backup & Restoration Engine for NOEI CMS.
 * Runs on low-cost shared hosting without requiring mysqldump or shell exec access.
 */
class BackupService
{
    private string $backupDir;
    private string $rootDir;

    public function __construct(?string $backupDir = null, ?string $rootDir = null)
    {
        $this->rootDir = rtrim($rootDir ?? dirname(__DIR__, 2), '/\\');
        $this->backupDir = rtrim($backupDir ?? "{$this->rootDir}/storage/backups", '/\\');

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Create a pure-PHP SQL dump of the entire database.
     *
     * @return string Path to generated .sql backup file
     */
    public function createDatabaseBackup(): string
    {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $timestamp = date('Y-m-d-His');
        $filename = "db-backup-{$timestamp}.sql";
        $filePath = "{$this->backupDir}/{$filename}";

        $out = "-- NOEI CMS Pure-PHP Database Dump\n";
        $out .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
        $out .= "-- Driver: {$driver}\n\n";

        if ($driver === 'mysql') {
            $out .= "SET FOREIGN_KEY_CHECKS = 0;\n";
            $out .= "SET NAMES utf8mb4;\n\n";

            $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                // Table DDL
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
                $createSql = $createRow['Create Table'] ?? '';

                $out .= "-- Table structure for `{$table}`\n";
                $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $out .= "{$createSql};\n\n";

                // Table Data Rows
                $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
                $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $out .= "-- Data rows for `{$table}`\n";
                    foreach ($rows as $row) {
                        $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
                        $vals = array_map(function ($val) use ($pdo) {
                            if ($val === null) {
                                return 'NULL';
                            }
                            return $pdo->quote((string)$val);
                        }, array_values($row));

                        $out .= "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
                    }
                    $out .= "\n";
                }
            }

            $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        } else {
            // SQLite driver support (used in test suite)
            $stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($tables as $t) {
                $table = $t['name'];
                $createSql = $t['sql'];

                $out .= "-- Table structure for {$table}\n";
                $out .= "DROP TABLE IF EXISTS {$table};\n";
                $out .= "{$createSql};\n\n";

                $dataStmt = $pdo->query("SELECT * FROM \"{$table}\"");
                $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $out .= "-- Data rows for {$table}\n";
                    foreach ($rows as $row) {
                        $cols = array_keys($row);
                        $vals = array_map(function ($val) use ($pdo) {
                            if ($val === null) {
                                return 'NULL';
                            }
                            return $pdo->quote((string)$val);
                        }, array_values($row));

                        $out .= "INSERT INTO \"{$table}\" (\"" . implode('", "', $cols) . "\") VALUES (" . implode(', ', $vals) . ");\n";
                    }
                    $out .= "\n";
                }
            }
        }

        if (file_put_contents($filePath, $out) === false) {
            throw new RuntimeException("Failed to write database backup file to [{$filePath}].");
        }

        return $filePath;
    }

    /**
     * Create a full backup (Core application files + database SQL dump) in a ZIP file.
     *
     * @return string Path to generated .zip backup file
     */
    public function createFullBackup(): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("ZipArchive extension is required for full site backups.");
        }

        // 1. Create SQL dump
        $sqlPath = $this->createDatabaseBackup();

        $timestamp = date('Y-m-d-His');
        $zipFilename = "full-backup-{$timestamp}.zip";
        $zipPath = "{$this->backupDir}/{$zipFilename}";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create full backup ZIP file [{$zipPath}].");
        }

        // Add SQL dump to ZIP root
        $zip->addFile($sqlPath, basename($sqlPath));

        // Add application directories
        $foldersToBackup = ['app', 'config', 'core', 'themes', 'modules', 'storage/uploads'];

        foreach ($foldersToBackup as $folder) {
            $fullFolderPath = "{$this->rootDir}/{$folder}";
            if (is_dir($fullFolderPath)) {
                $this->addFolderToZip($fullFolderPath, $folder, $zip);
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Restore database from a specific SQL backup file.
     *
     * @param string $filename
     * @return bool
     */
    public function restoreDatabase(string $filename): bool
    {
        $safeFilename = basename($filename);
        $filePath = "{$this->backupDir}/{$safeFilename}";

        if (!file_exists($filePath)) {
            return false;
        }

        $sqlContent = file_get_contents($filePath);
        if ($sqlContent === false || empty($sqlContent)) {
            return false;
        }

        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            // Strip comments and execute queries sequentially
            $cleanSql = preg_replace('/--.*$/m', '', $sqlContent);
            $statements = array_filter(array_map('trim', explode(';', (string)$cleanSql)));

            if ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            }

            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    $pdo->exec($stmt);
                }
            }

            if ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            }

            return true;
        } catch (\Throwable $e) {
            error_log("NOEI Database Restoration Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of all available backups.
     *
     * @return array<array>
     */
    public function listBackups(): array
    {
        $files = glob("{$this->backupDir}/*.*") ?: [];
        $backups = [];

        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['sql', 'zip'], true)) {
                continue;
            }

            $filename = basename($file);
            $sizeBytes = filesize($file) ?: 0;
            $mtime = filemtime($file) ?: time();

            $type = str_starts_with($filename, 'full-') ? 'Full Archive' : (str_starts_with($filename, 'pre-update') ? 'Pre-Update Snapshot' : 'Database Dump');

            $backups[] = [
                'filename' => $filename,
                'path' => str_replace('\\', '/', $file),
                'type' => $type,
                'extension' => $ext,
                'size_bytes' => $sizeBytes,
                'size_formatted' => $this->formatBytes($sizeBytes),
                'created_at' => date('Y-m-d H:i:s', $mtime),
                'timestamp' => $mtime,
            ];
        }

        // Sort latest backups first
        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    /**
     * Delete a backup file.
     *
     * @param string $filename
     * @return bool
     */
    public function deleteBackup(string $filename): bool
    {
        $safeFilename = basename($filename);
        $filePath = "{$this->backupDir}/{$safeFilename}";

        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return false;
    }

    /**
     * Get absolute path of a backup file for downloading.
     *
     * @param string $filename
     * @return string|null
     */
    public function getBackupPath(string $filename): ?string
    {
        $safeFilename = basename($filename);
        $filePath = "{$this->backupDir}/{$safeFilename}";
        return file_exists($filePath) ? $filePath : null;
    }

    /**
     * Helper adding a directory recursively to ZipArchive.
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

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return "{$bytes} B";
    }
}
