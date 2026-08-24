<?php

declare(strict_types=1);

/**
 * NOEI CMS - Standalone Release Distribution Builder.
 * Compiles a clean, installable shared-hosting zip archive.
 *
 * Usage: php tools/build-release.php [version]
 */

$rootDir = dirname(__DIR__);
$version = $argv[1] ?? '1.0.0';
$distDir = "{$rootDir}/dist";

if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

$zipFileName = "noei-cms-v{$version}.zip";
$zipFilePath = "{$distDir}/{$zipFileName}";

echo "=== NOEI CMS Release Builder ===\n";
echo "Version: {$version}\n";
echo "Destination: {$zipFilePath}\n\n";

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Error: ZipArchive PHP extension is required to build release.\n");
    exit(1);
}

if (file_exists($zipFilePath)) {
    @unlink($zipFilePath);
}

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Error: Cannot open ZIP file [{$zipFilePath}] for writing.\n");
    exit(1);
}

// Folders to include in distribution
$directories = [
    'app',
    'config',
    'core',
    'install',
    'modules',
    'public',
    'storage',
    'themes',
];

// Single root files to include
$rootFiles = [
    '.htaccess',
    'index.php',
    'robots.txt',
    'README.md',
    'LICENSE',
];

// Excluded patterns
$exclusions = [
    '/config\/database\.php$/i',
    '/storage\/installed\.lock$/i',
    '/storage\/logs\/.*\.log$/i',
    '/storage\/backups\/.*\.(sql|zip)$/i',
    '/storage\/cache\/.*\.xml$/i',
    '/storage\/uploads\/.+\..+$/i', // user uploaded files (keep .htaccess & .gitkeep)
    '/\.DS_Store$/i',
    '/Thumbs\.db$/i',
];

function isExcluded(string $relativePath, array $exclusions): bool
{
    // Never exclude .gitkeep and .htaccess in storage
    if (str_ends_with($relativePath, '.gitkeep') || str_ends_with($relativePath, 'storage/uploads/.htaccess')) {
        return false;
    }

    foreach ($exclusions as $pattern) {
        if (preg_match($pattern, $relativePath)) {
            return true;
        }
    }
    return false;
}

$fileCount = 0;

// Add directories recursively
foreach ($directories as $dirName) {
    $dirFullPath = "{$rootDir}/{$dirName}";
    if (!is_dir($dirFullPath)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirFullPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $realPath = str_replace('\\', '/', $file->getRealPath());
        $relativePath = substr($realPath, strlen(str_replace('\\', '/', $rootDir)) + 1);

        if (isExcluded($relativePath, $exclusions)) {
            continue;
        }

        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($realPath, $relativePath);
            $fileCount++;
        }
    }
}

// Add root files
foreach ($rootFiles as $file) {
    $fullPath = "{$rootDir}/{$file}";
    if (file_exists($fullPath)) {
        $zip->addFile($fullPath, $file);
        $fileCount++;
    }
}

// Ensure database.sample.php is present in config
if (file_exists("{$rootDir}/config/database.sample.php")) {
    $zip->addFile("{$rootDir}/config/database.sample.php", "config/database.sample.php");
}

$zip->close();

$fileSizeMb = round(filesize($zipFilePath) / 1048576, 2);
echo "[SUCCESS] Release package compiled successfully!\n";
echo " - File: dist/{$zipFileName}\n";
echo " - Size: {$fileSizeMb} MB\n";
echo " - Packaged Files: {$fileCount}\n\n";

// Also copy as noei-cms-latest.zip
copy($zipFilePath, "{$distDir}/noei-cms-latest.zip");
echo "[SUCCESS] Copied to dist/noei-cms-latest.zip\n";
exit(0);
