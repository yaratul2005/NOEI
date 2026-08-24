<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for Milestone 5:
 * Media Management, Secure Upload Pipeline, Responsive Image Engine (GD), and File Cleanup.
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Models\Media;
use App\Services\ImageService;
use App\Services\MediaService;
use Core\Database;

$passed = 0;
$failed = 0;

function assertM5(bool $condition, string $description): void
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

echo "=== NOEI CMS Milestone 5 Verification Suite ===\n\n";

// 1. Initialize In-Memory SQLite Database & Test Environment
echo "[1] Setting up In-Memory Database for Media Engine...\n";
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

$testUploadDir = __DIR__ . '/../storage/uploads';
if (!is_dir($testUploadDir)) {
    mkdir($testUploadDir, 0755, true);
}

$mediaService = new MediaService(new Media($db), new ImageService(), $testUploadDir);

// 2. Security Rejection Tests
echo "\n[2] Testing Security Validation (Executable Extensions & MIME Rejection)...\n";

// Test 2.1: Executable .php upload attempt
$tmpPhpFile = sys_get_temp_dir() . '/exploit.php';
file_put_contents($tmpPhpFile, "<?php echo 'hacked'; ?>");

$rejectedPhp = false;
try {
    $mediaService->upload([
        'name' => 'exploit.php',
        'type' => 'application/x-php',
        'tmp_name' => $tmpPhpFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tmpPhpFile),
    ], 1);
} catch (\Throwable $e) {
    $rejectedPhp = true;
}
@unlink($tmpPhpFile);
assertM5($rejectedPhp === true, "MediaService rejects executable .php file extension upload attempt");

// Test 2.2: Extension whitelist rejection
$tmpExeFile = sys_get_temp_dir() . '/bad_file.exe';
file_put_contents($tmpExeFile, "binary content");

$rejectedExe = false;
try {
    $mediaService->upload([
        'name' => 'bad_file.exe',
        'type' => 'application/x-msdownload',
        'tmp_name' => $tmpExeFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tmpExeFile),
    ], 1);
} catch (\Throwable $e) {
    $rejectedExe = true;
}
@unlink($tmpExeFile);
assertM5($rejectedExe === true, "MediaService rejects non-whitelisted extension (.exe)");

// 3. Valid Upload & Storage Hierarchy Tests
echo "\n[3] Testing Valid File Upload & Storage Placement...\n";

// Create dummy valid PNG image for upload testing
$tmpPngFile = sys_get_temp_dir() . '/sample_test.png';
$samplePngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($tmpPngFile, $samplePngData);

$uploadedRecord = $mediaService->upload([
    'name' => 'sample_test.png',
    'type' => 'image/png',
    'tmp_name' => $tmpPngFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpPngFile),
], 1);
@unlink($tmpPngFile);

assertM5($uploadedRecord['id'] > 0, "MediaService saves record in cms_media database table");
assertM5(file_exists(__DIR__ . '/../' . $uploadedRecord['file_path']), "Uploaded original file exists in storage hierarchy");

// 4. Image Resizing & Responsive Variant Tests
echo "\n[4] Testing Responsive Image Variant Generation (GD Engine)...\n";
$meta = $uploadedRecord['meta_data'];
$hasVariants = isset($meta['variants']['thumbnail']);
assertM5($hasVariants === true, "ImageService generates responsive thumbnail variant");

if ($hasVariants) {
    $thumbPath = __DIR__ . '/../' . $meta['variants']['thumbnail'];
    assertM5(file_exists($thumbPath), "Physical thumbnail image variant exists on disk");
}

// 5. Physical File Cleanup on Deletion
echo "\n[5] Testing File Variant Cleanup on Media Deletion...\n";
$mediaId = $uploadedRecord['id'];
$origFilePath = __DIR__ . '/../' . $uploadedRecord['file_path'];
$thumbFilePath = $hasVariants ? __DIR__ . '/../' . $meta['variants']['thumbnail'] : null;

$deleted = $mediaService->deleteMedia($mediaId);
assertM5($deleted === true, "MediaService deleteMedia() removes database record");
assertM5(!file_exists($origFilePath), "MediaService deletes physical original file from disk");

if ($thumbFilePath) {
    assertM5(!file_exists($thumbFilePath), "MediaService cleans up all physical thumbnail variant files from disk");
}

// Cleanup test upload directory
array_map('unlink', glob("{$testUploadDir}/*/*/*.*"));
array_map('rmdir', glob("{$testUploadDir}/*/*"));
array_map('rmdir', glob("{$testUploadDir}/*"));
@rmdir($testUploadDir);

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
