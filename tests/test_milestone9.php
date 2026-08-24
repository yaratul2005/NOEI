<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for Milestone 9:
 * Pure-PHP Database Dumper, Restoration, Pre-Update Snapshot, and Rollback System.
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Services\BackupService;
use App\Services\UpdateService;
use Core\Database;

$passed = 0;
$failed = 0;

function assertM9(bool $condition, string $description): void
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passed++;
    } else {
        echo " [FAIL] {$description}\n";
        $failed++;
    }
}

echo "=== NOEI CMS Milestone 9 Verification Suite ===\n\n";

// 1. Initialize In-Memory Database
echo "[1] Setting up In-Memory Database for Backup Engine...\n";
$sqlitePdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$schemaSql = file_get_contents(__DIR__ . '/../install/schema.sql');
$cleanSchema = preg_replace('/--.*$/m', '', $schemaSql);
$sqliteSql = preg_replace('/ENGINE=InnoDB DEFAULT CHARSET=\w+ COLLATE=\w+;/i', ';', $cleanSchema);
$sqliteSql = preg_replace('/,?\s*KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sqliteSql);
$sqliteSql = str_replace(
    ['LONGTEXT', 'ON UPDATE CURRENT_TIMESTAMP', 'BIGINT AUTO_INCREMENT PRIMARY KEY', 'INT AUTO_INCREMENT PRIMARY KEY'],
    ['TEXT', '', 'INTEGER PRIMARY KEY AUTOINCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT'],
    $sqliteSql
);

$statements = array_filter(array_map('trim', explode(';', $sqliteSql)));
foreach ($statements as $stmt) {
    if (!empty($stmt) && !str_starts_with(strtoupper($stmt), 'SET ')) {
        $sqlitePdo->exec($stmt);
    }
}

Database::setPdo($sqlitePdo);
$db = Database::getInstance();

// Insert test record
$db->execute("INSERT INTO cms_posts (author_id, title, slug, content, type, status) VALUES (1, 'Backup Test Post', 'backup-test-post', 'Content to backup', 'post', 'published')");

$testBackupDir = __DIR__ . '/../storage/backups/test_backup_run';
if (!is_dir($testBackupDir)) {
    mkdir($testBackupDir, 0755, true);
}

$backupService = new BackupService($testBackupDir, dirname(__DIR__));

// 2. Testing Database SQL Backup Dumper
echo "\n[2] Testing Pure-PHP Database SQL Backup Generation...\n";
$sqlPath = $backupService->createDatabaseBackup();
assertM9(file_exists($sqlPath), "BackupService generates database SQL dump file on disk");

$sqlContent = file_get_contents($sqlPath);
assertM9(str_contains($sqlContent, 'cms_posts') && str_contains($sqlContent, 'Backup Test Post'), "SQL dump file contains DDL tables and DML data rows");

// 3. Testing Database Restoration
echo "\n[3] Testing Database Restoration from SQL Dump...\n";
// Delete post to test restore
$db->execute("DELETE FROM cms_posts WHERE slug = 'backup-test-post'");
$checkEmpty = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_posts WHERE slug = 'backup-test-post'");
assertM9($checkEmpty === 0, "Post deleted prior to restoration");

$restored = $backupService->restoreDatabase(basename($sqlPath));
assertM9($restored === true, "BackupService restoreDatabase() executes successfully");

$restoredCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_posts WHERE slug = 'backup-test-post'");
assertM9($restoredCount === 1, "Deleted record restored back into database from SQL backup dump");

// 4. Testing Backup List & Cleanup
echo "\n[4] Testing Backup Listing & Deletion...\n";
$backups = $backupService->listBackups();
assertM9(count($backups) >= 1 && $backups[0]['filename'] === basename($sqlPath), "BackupService listBackups() returns generated backup metadata");

$deleted = $backupService->deleteBackup(basename($sqlPath));
assertM9($deleted === true && !file_exists($sqlPath), "BackupService deleteBackup() removes physical backup file from storage");

// 5. Testing UpdateService & Pre-Update Snapshot
echo "\n[5] Testing UpdateService Version Check & Pre-Update Safety Snapshot...\n";
$updateService = new UpdateService(dirname(__DIR__), $backupService);

$check = $updateService->checkForUpdates();
assertM9(isset($check['current_version']) && $check['has_update'] === false, "UpdateService reports current installed CMS version");

if (class_exists('ZipArchive')) {
    $snapshotPath = $updateService->createPreUpdateSnapshot();
    assertM9(file_exists($snapshotPath), "UpdateService creates pre-update snapshot ZIP file in storage/backups");
    @unlink($snapshotPath);
} else {
    assertM9(true, "ZipArchive check skipped on environments without native zip");
}

// Clean up test backup directory
array_map('unlink', glob("{$testBackupDir}/*.*"));
@rmdir($testBackupDir);

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
