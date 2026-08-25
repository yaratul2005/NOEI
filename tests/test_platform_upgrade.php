<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for NOEI CMS Platform Upgrade:
 * cPanel Resilience, Subfolder Routing, WordPress-Grade Customization, Shortcodes, and Site Health.
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Controllers\Admin\HealthController;
use App\Services\OptionService;
use Core\Database;
use Core\Event;
use Core\PostType;
use Core\Request;
use Core\Shortcode;
use Core\Storage;

$passed = 0;
$failed = 0;

function assertUpgrade(bool $condition, string $description): void
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

echo "=== NOEI CMS Platform Upgrade Verification Suite ===\n\n";

// 1. Initialize In-Memory Database
echo "[1] Setting up In-Memory Database for Platform Upgrade Tests...\n";
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

// 2. Testing Dynamic Base Path & Subfolder Resolution
echo "\n[2] Testing Dynamic Base Path & Subfolder Resolution...\n";

// Scenario A: Installed in Domain Root (/)
$rootReq = new Request([], [], [
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/admin/posts',
    'HTTP_HOST' => 'example.com',
]);
assertUpgrade($rootReq->getBasePath() === '', "Root installation detects empty base path");
assertUpgrade($rootReq->getPath() === '/admin/posts', "Root installation resolves /admin/posts clean path");
assertUpgrade($rootReq->getBaseUrl() === 'http://example.com', "Root installation builds base URL http://example.com");

// Scenario B: Installed in Subfolder (/noei/ or /cms/)
$subReq = new Request([], [], [
    'SCRIPT_NAME' => '/noei/index.php',
    'REQUEST_URI' => '/noei/admin/posts?page=2',
    'HTTP_HOST' => 'example.com',
]);
assertUpgrade($subReq->getBasePath() === '/noei', "Subfolder installation automatically extracts '/noei' base path");
assertUpgrade($subReq->getPath() === '/admin/posts', "Subfolder installation strips '/noei' and resolves relative route path '/admin/posts'");
assertUpgrade($subReq->getBaseUrl() === 'http://example.com/noei', "Subfolder installation builds correct base URL http://example.com/noei");

// Helper base_url() and url() checks
$_SERVER['SCRIPT_NAME'] = '/cms/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
assertUpgrade(base_url('/posts/hello') === '/cms/posts/hello', "base_url() helper dynamically prepends subfolder prefix");
assertUpgrade(url('/posts/hello') === 'http://localhost/cms/posts/hello', "url() helper generates full absolute URL with subfolder prefix");

// 3. Testing Bulletproof HTTPS & Reverse Proxy Detection
echo "\n[3] Testing Bulletproof HTTPS & Reverse Proxy Detection...\n";

// Direct HTTPS
$directHttps = new Request([], [], ['HTTPS' => 'on', 'HTTP_HOST' => 'secure.com']);
assertUpgrade($directHttps->isHttps() === true && $directHttps->getScheme() === 'https', "Direct HTTPS connection identified");

// Reverse Proxy (X-Forwarded-Proto)
$proxyHttps = new Request([], [], ['HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_HOST' => 'proxy.com']);
assertUpgrade($proxyHttps->isHttps() === true, "X-Forwarded-Proto reverse proxy identified as HTTPS");

// Cloudflare (CF-Visitor)
$cfHttps = new Request([], [], ['HTTP_CF_VISITOR' => '{"scheme":"https"}', 'HTTP_HOST' => 'cf.com']);
assertUpgrade($cfHttps->isHttps() === true, "Cloudflare CF-Visitor JSON header identified as HTTPS");

// Insecure HTTP
$httpReq = new Request([], [], ['HTTPS' => 'off', 'HTTP_HOST' => 'insecure.com', 'SERVER_PORT' => '80']);
assertUpgrade($httpReq->isHttps() === false && $httpReq->getScheme() === 'http', "Insecure HTTP connection identified properly");

// 4. Testing Storage Auto-Healing & Permissions
echo "\n[4] Testing Storage Auto-Healing & Permissions...\n";
$tempTestRoot = sys_get_temp_dir() . '/noei_test_storage_' . uniqid();
Storage::ensureDirectories($tempTestRoot);

assertUpgrade(is_dir("{$tempTestRoot}/storage/cache"), "Storage::ensureDirectories creates missing storage/cache directory");
assertUpgrade(is_dir("{$tempTestRoot}/storage/uploads"), "Storage::ensureDirectories creates missing storage/uploads directory");
assertUpgrade(file_exists("{$tempTestRoot}/storage/uploads/.htaccess"), "Storage::ensureDirectories installs script execution denial .htaccess in uploads");

// Cleanup test temp directory
@unlink("{$tempTestRoot}/storage/uploads/.htaccess");
@unlink("{$tempTestRoot}/storage/uploads/.gitkeep");
@unlink("{$tempTestRoot}/storage/cache/.gitkeep");
@unlink("{$tempTestRoot}/storage/logs/.gitkeep");
@unlink("{$tempTestRoot}/storage/backups/.gitkeep");
@rmdir("{$tempTestRoot}/storage/uploads");
@rmdir("{$tempTestRoot}/storage/cache");
@rmdir("{$tempTestRoot}/storage/logs");
@rmdir("{$tempTestRoot}/storage/backups");
@rmdir("{$tempTestRoot}/storage");
@rmdir($tempTestRoot);

// 5. Testing WordPress-Grade Shortcode Engine
echo "\n[5] Testing WordPress-Grade Shortcode Engine...\n";
Shortcode::initDefaults();

// Test [button] shortcode
$btnContent = do_shortcode('[button text="Explore Features" url="https://noei.io" style="secondary" target="_blank"]');
assertUpgrade(str_contains($btnContent, 'href="https://noei.io"') && str_contains($btnContent, 'btn btn-secondary') && str_contains($btnContent, 'Explore Features'), "[button] shortcode renders styled anchor tag");

// Test [notice] enclosing shortcode
$noticeContent = do_shortcode('[notice type="warning"]Important notice text here[/notice]');
assertUpgrade(str_contains($noticeContent, 'notice-warning') && str_contains($noticeContent, 'Important notice text here'), "[notice] enclosing shortcode renders callout alert box");

// Test [quote] shortcode
$quoteContent = do_shortcode('[quote author="Ada Lovelace"]Imagination is the Discovering Faculty.[/quote]');
assertUpgrade(str_contains($quoteContent, '<blockquote') && str_contains($quoteContent, 'Ada Lovelace'), "[quote] shortcode renders blockquote with author citation");

// Custom shortcode registration
add_shortcode('greet', function ($attrs) {
    $name = $attrs['name'] ?? 'World';
    return "<span>Hello, {$name}!</span>";
});
$customContent = do_shortcode('Welcome! [greet name="NOEI User"]');
assertUpgrade(str_contains($customContent, '<span>Hello, NOEI User!</span>'), "Custom add_shortcode() hook executes and transforms content");

// 6. Testing Custom Post Types (CPT) & Post Metadata
echo "\n[6] Testing Custom Post Types & Post Metadata Storage...\n";

// Register custom post type 'portfolio'
register_post_type('portfolio', [
    'label' => 'Portfolio Projects',
    'singular_label' => 'Portfolio Item',
    'icon' => '💼',
]);

$allTypes = get_post_types();
assertUpgrade(isset($allTypes['portfolio']) && $allTypes['portfolio']['label'] === 'Portfolio Projects', "register_post_type() registers custom post type metadata");

// Test cms_post_meta CRUD helpers
$postId = 42;
$savedMeta = update_post_meta($postId, '_featured_project', 'yes');
assertUpgrade($savedMeta === true, "update_post_meta() inserts custom metadata record");

$retrievedMeta = get_post_meta($postId, '_featured_project');
assertUpgrade($retrievedMeta === 'yes', "get_post_meta() retrieves stored custom metadata value");

$deletedMeta = delete_post_meta($postId, '_featured_project');
assertUpgrade($deletedMeta === true && get_post_meta($postId, '_featured_project') === null, "delete_post_meta() removes metadata record");

// 7. Testing Visual Theme Customizer & Site Health Diagnostics
echo "\n[7] Testing Visual Customizer & Site Health Diagnostics...\n";

OptionService::set('theme_primary_color', '#10b981');
OptionService::set('custom_css', '.hero { padding: 40px; }');

assertUpgrade(option('theme_primary_color') === '#10b981', "OptionService stores custom theme primary color");
assertUpgrade(str_contains((string)option('custom_css'), '.hero'), "OptionService stores custom CSS injections");

$health = new HealthController();
$checks = $health->runHealthChecks(new Request([], [], ['HTTPS' => 'on']));
assertUpgrade(!empty($checks), "HealthController runHealthChecks() executes system diagnostics");
assertUpgrade(isset($checks[0]['status']), "Diagnostic checks contain standardized status badges");

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
