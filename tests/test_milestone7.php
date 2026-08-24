<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for Milestone 7:
 * Options Service, SEO Metadata Generator, Dynamic XML Sitemap (with Cache), Robots.txt, and Reading Settings.
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Controllers\PublicController;
use App\Controllers\SeoController;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Services\OptionService;
use App\Services\SeoService;
use App\Services\ThemeService;
use Core\Database;
use Core\Request;

$passed = 0;
$failed = 0;

function assertM7(bool $condition, string $description): void
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

echo "=== NOEI CMS Milestone 7 Verification Suite ===\n\n";

// 1. Initialize In-Memory Database
echo "[1] Setting up In-Memory Database for Options & SEO...\n";
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

// Reset OptionService memory cache
OptionService::clearCache();

// 2. Testing OptionService Autoloading & Key-Value Management
echo "\n[2] Testing OptionService (Single-query Autoloading & Key-Value Storage)...\n";
$siteTitle = OptionService::get('site_title');
assertM7($siteTitle === 'NOEI CMS Site', "OptionService autoloads pre-seeded options in single initial query");

OptionService::set('custom_key', 'custom_value', true);
assertM7(OptionService::get('custom_key') === 'custom_value', "OptionService set() stores and caches new key-value pair");

OptionService::delete('custom_key');
assertM7(OptionService::get('custom_key', 'default_val') === 'default_val', "OptionService delete() removes option and returns fallback default");

// 3. Testing SeoService Dynamic Metadata & Social Sharing Tags
echo "\n[3] Testing SeoService Dynamic Metadata, Open Graph & Twitter Cards...\n";
OptionService::set('site_title', 'My Awesome NOEI Site');
OptionService::set('site_url', 'https://example.com');
OptionService::set('seo_description', 'A super fast CMS.');

// 3.1 Homepage SEO Tags
$homeHead = SeoService::renderHead([]);
assertM7(str_contains($homeHead, '<title>My Awesome NOEI Site</title>'), "SeoService generates homepage title tag");
assertM7(str_contains($homeHead, '<link rel="canonical" href="https://example.com/">'), "SeoService outputs canonical URL tag");
assertM7(str_contains($homeHead, '<meta property="og:site_name" content="My Awesome NOEI Site">'), "SeoService outputs Open Graph site_name");
assertM7(str_contains($homeHead, '<meta name="twitter:card" content="summary_large_image">'), "SeoService outputs Twitter card tag");

// 3.2 Single Post with Custom Meta Override
$db->execute("INSERT INTO cms_posts (author_id, title, slug, content, excerpt, type, status) VALUES (1, 'SEO Guide 2026', 'seo-guide-2026', 'Post content body', 'Short summary.', 'post', 'published')");
$postId = (int)$db->lastInsertId();
$db->execute("INSERT INTO cms_post_meta (post_id, meta_key, meta_value) VALUES (:id, 'seo_title', 'Custom High Ranking Title')", ['id' => $postId]);

$post = $db->fetch("SELECT * FROM cms_posts WHERE id = :id", ['id' => $postId]);
$postHead = SeoService::renderHead(['post' => $post]);
assertM7(str_contains($postHead, '<title>Custom High Ranking Title</title>'), "SeoService respects custom seo_title override from cms_post_meta");
assertM7(str_contains($postHead, '<meta property="og:type" content="article">'), "SeoService sets og:type to article for post content");

// 4. Testing Dynamic XML Sitemap (with File Cache) & Robots.txt
echo "\n[4] Testing Dynamic XML Sitemap & Robots.txt...\n";
$seoController = new SeoController();
$req = new Request([], [], [], [], ['REQUEST_URI' => '/sitemap.xml']);

// Clear existing cache if any
SeoController::clearSitemapCache();

$sitemapRes = $seoController->sitemap($req);
assertM7($sitemapRes->getStatusCode() === 200, "SeoController sitemap() returns HTTP 200");
assertM7(str_contains($sitemapRes->getContent(), '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'), "Sitemap conforms to sitemaps.org XML schema");
assertM7(str_contains($sitemapRes->getContent(), '<loc>https://example.com/post/seo-guide-2026</loc>'), "Sitemap contains published post URL");

$cachePath = dirname(__DIR__) . '/storage/cache/sitemap.xml';
assertM7(file_exists($cachePath), "Sitemap successfully generates and caches file to storage/cache/sitemap.xml");

// Robots.txt
$robotsReq = new Request([], [], [], [], ['REQUEST_URI' => '/robots.txt']);
$robotsRes = $seoController->robots($robotsReq);
assertM7($robotsRes->getStatusCode() === 200, "SeoController robots() returns HTTP 200");
assertM7(str_contains($robotsRes->getContent(), 'Disallow: /admin/') && str_contains($robotsRes->getContent(), 'Sitemap: https://example.com/sitemap.xml'), "Robots.txt emits disallow directives and sitemap reference");

// 5. Testing Reading Settings & Static Homepage Behavior
echo "\n[5] Testing Reading Settings & Static Homepage Switch...\n";
$postModel = new Post($db);
$pageId = $postModel->create([
    'author_id' => 1,
    'title' => 'Static Welcome Page',
    'slug' => 'welcome-home',
    'content' => 'This is the static homepage content.',
    'type' => 'page',
    'status' => 'published',
]);

// Switch reading settings to static page
OptionService::set('homepage_type', 'page');
OptionService::set('homepage_page_id', $pageId);

$themeService = new ThemeService();
$pubController = new PublicController($postModel, new Taxonomy($db), $themeService);

$homeReq = new Request([], [], [], [], ['REQUEST_URI' => '/']);
$homePageRes = $pubController->index($homeReq);
assertM7(str_contains($homePageRes->getContent(), 'Static Welcome Page'), "PublicController serves designated static page when homepage_type is 'page'");

// Cleanup sitemap cache
SeoController::clearSitemapCache();

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
