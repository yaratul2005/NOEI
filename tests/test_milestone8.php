<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for Milestone 8:
 * Module Engine, Manifest Discovery, Lifecycle Management, and Core Event Hook Integration.
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Services\ModuleService;
use App\Services\OptionService;
use Core\Database;
use Core\Event;

$passed = 0;
$failed = 0;

function assertM8(bool $condition, string $description): void
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

echo "=== NOEI CMS Milestone 8 Verification Suite ===\n\n";

// 1. Initialize In-Memory Database
echo "[1] Setting up In-Memory Database for Module Engine...\n";
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
OptionService::clearCache();

// 2. Testing Module Discovery & Manifest Parsing
echo "\n[2] Testing Module Discovery & Manifest Parsing...\n";
$moduleService = new ModuleService();

$discovered = $moduleService->getDiscoveredModules();
assertM8(isset($discovered['sample-notice']), "ModuleService discovers 'sample-notice' demo module in modules directory");

$sampleNotice = $discovered['sample-notice'];
assertM8($sampleNotice['version'] === '1.0.0', "ModuleService parses module.json version correctly");
assertM8(in_array('content:filter', $sampleNotice['permissions'], true), "ModuleService extracts declared permissions array");

// 3. Testing Module Activation & Option Persistence
echo "\n[3] Testing Module Activation & Deactivation Lifecycle...\n";
assertM8(count($moduleService->getActiveSlugs()) === 0, "No modules active by default");

$activated = $moduleService->activate('sample-notice');
assertM8($activated === true, "ModuleService activate() returns true for valid module");
assertM8(in_array('sample-notice', $moduleService->getActiveSlugs(), true), "Active module slug persisted to cms_options");

// 4. Testing Event Hook Execution by Active Module
echo "\n[4] Testing Event Hook Transformation by Active Module...\n";
$moduleService->bootActiveModules();

$rawContent = "This is a detailed post body containing multiple words to calculate accurate reading time metrics.";
$filteredContent = Event::applyFilters('the_content', $rawContent);

assertM8(str_contains($filteredContent, 'sample-notice-box') && str_contains($filteredContent, 'Reading Time:'), "SampleNoticeModule dynamically filters 'the_content' via Event hook");

// 5. Testing Module Deactivation & Hook Removal
echo "\n[5] Testing Module Deactivation & Isolation...\n";
$deactivated = $moduleService->deactivate('sample-notice');
assertM8($deactivated === true, "ModuleService deactivate() succeeds");
assertM8(!in_array('sample-notice', $moduleService->getActiveSlugs(), true), "Deactivated module slug removed from cms_options");

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
