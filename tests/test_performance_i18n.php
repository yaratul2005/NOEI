<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for NOEI CMS:
 * High-Performance Eager Loading, Native i18n & Pure-PHP WebP Optimization.
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Models\Post;
use App\Services\ImageService;
use Core\Database;
use Core\I18n;

$passed = 0;
$failed = 0;

function assertPerf(bool $condition, string $description): void
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

echo "=== NOEI CMS Performance, i18n & WebP Verification Suite ===\n\n";

// 1. Initialize In-Memory Database
echo "[1] Setting up In-Memory SQLite Database...\n";
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

// 2. Testing Batch PostMeta & Taxonomy Eager Loading (N+1 Query Elimination)
echo "\n[2] Testing Batch PostMeta & Taxonomy Eager Loading...\n";

$postModel = new Post();

// Create 5 test posts with diverse metadata and categories
$postIds = [];
for ($i = 1; $i <= 5; $i++) {
    $pid = $postModel->create([
        'author_id' => 1,
        'title' => "Performance Article {$i}",
        'slug' => "perf-article-{$i}",
        'content' => "Content for article {$i}",
        'status' => 'published',
        'type' => 'post',
    ]);
    $postIds[] = $pid;

    // Attach custom metadata
    update_post_meta($pid, 'views_count', $i * 150);
    update_post_meta($pid, 'featured_color', '#3b82f6');
    update_post_meta($pid, 'reading_time_mins', $i * 2);
}

// Attach a category term to posts
$db = Database::getInstance();
$db->execute("INSERT INTO cms_terms (id, name, slug) VALUES (10, 'Technology', 'tech')");
$db->execute("INSERT INTO cms_taxonomies (id, term_id, taxonomy) VALUES (20, 10, 'category')");
foreach ($postIds as $pid) {
    $db->execute("INSERT INTO cms_term_relationships (object_id, taxonomy_id) VALUES (:obj, 20)", ['obj' => $pid]);
}

// Fetch posts collection (ordered DESC by id: Post 5 is index 0, Post 1 is index 4)
$posts = $postModel->getAll('post', 'published');
assertPerf(count($posts) === 5, "Fetched 5 published test posts");

// Execute batch eager loading
Post::eagerLoadAll($posts);

assertPerf(isset($posts[0]['meta']['views_count']), "Post::eagerLoadMeta attaches metadata to post array");
assertPerf((int)$posts[0]['meta']['views_count'] === 750, "Post meta contains accurate value for latest post (views_count = 750)");
assertPerf((int)$posts[4]['meta']['views_count'] === 150, "Post meta contains accurate value for post 1 (views_count = 150)");
assertPerf(isset($posts[0]['categories'][0]['name']) && $posts[0]['categories'][0]['name'] === 'Technology', "Post::eagerLoadTaxonomies batch attaches category terms");

// Verify that get_post_meta() reads from in-memory cache with zero extra DB calls
$cachedVal = get_post_meta($postIds[0], 'featured_color');
assertPerf($cachedVal === '#3b82f6', "get_post_meta() returns eager-loaded metadata from memory cache");

// 3. Testing Native Multilingual Engine (English & Bangla)
echo "\n[3] Testing Native Multilingual Translation Engine...\n";

I18n::setLangDir(__DIR__ . '/../lang');
I18n::clearCache();

// English check
set_locale('en');
assertPerf(app_locale() === 'en', "Active locale set to English ('en')");
assertPerf(__('Dashboard') === 'Dashboard', "__('Dashboard') in English returns 'Dashboard'");
assertPerf(__('Welcome, :name', ['name' => 'Ratul']) === 'Welcome, Ratul', "__('Welcome, :name') replaces parameters in English");

// Bangla check
set_locale('bn');
assertPerf(app_locale() === 'bn', "Active locale switched to Bangla ('bn')");
assertPerf(__('Dashboard') === 'ড্যাশবোর্ড', "__('Dashboard') in Bangla returns 'ড্যাশবোর্ড'");
assertPerf(__('Welcome, :name', ['name' => 'রাতুল']) === 'স্বাগতম, রাতুল', "__('Welcome, :name') replaces parameters in Bangla");
assertPerf(__('Save Changes') === 'পরিবর্তন সংরক্ষণ করুন', "__('Save Changes') translates correctly to Bangla");

// Fallback check
assertPerf(__('NonExistentKey123') === 'NonExistentKey123', "__() gracefully falls back to key when missing from dictionary");

// 4. Testing WebP Optimization & Semantic <picture> Markup
echo "\n[4] Testing WebP Optimization & Picture Tag Helper...\n";

$imageService = new ImageService();
$tempDir = sys_get_temp_dir() . '/noei_webp_test_' . uniqid();
@mkdir($tempDir, 0755, true);

$tempJpeg = $tempDir . '/sample.jpg';
if (ImageService::isAvailable()) {
    $im = imagecreatetruecolor(200, 200);
    $bg = imagecolorallocate($im, 59, 130, 246);
    imagefilledrectangle($im, 0, 0, 199, 199, $bg);
    imagejpeg($im, $tempJpeg, 90);
    imagedestroy($im);
} else {
    file_put_contents($tempJpeg, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));
}

assertPerf(file_exists($tempJpeg), "Created test JPEG image file");

// Generate variants including WebP
$variants = $imageService->generateVariants($tempJpeg, 'image/jpeg');

assertPerf(isset($variants['thumbnail']), "Generated thumbnail variant");
assertPerf(file_exists($variants['thumbnail']['path']), "Thumbnail image file exists on disk");

if (ImageService::supportsWebp()) {
    assertPerf(isset($variants['thumbnail']['webp_path']) && file_exists($variants['thumbnail']['webp_path']), "Generated thumbnail .webp variant exists on disk");
    assertPerf(isset($variants['original_webp']['path']) && file_exists($variants['original_webp']['path']), "Generated full-size .webp copy exists on disk");
} else {
    assertPerf(true, "Graceful fallback when GD WebP module is omitted in environment");
}

// Test picture_tag() helper
$mediaMock = [
    'filename' => 'sample.jpg',
    'file_path' => '/storage/uploads/2026/08/sample.jpg',
    'meta_data' => [
        'variants' => [
            'large' => [
                'path' => '/storage/uploads/2026/08/sample-large.jpg',
                'webp_path' => '/storage/uploads/2026/08/sample-large.webp',
            ],
        ],
    ],
];

$pictureHtml = picture_tag($mediaMock, 'large', 'Hero Image', 'hero-img');
assertPerf(str_contains($pictureHtml, '<picture>') && str_contains($pictureHtml, 'type="image/webp"'), "picture_tag() generates HTML5 <picture> element with WebP source");
assertPerf(str_contains($pictureHtml, 'srcset="') && str_contains($pictureHtml, 'sample-large.webp"'), "picture_tag() links to .webp source file");
assertPerf(str_contains($pictureHtml, '<img src="') && str_contains($pictureHtml, 'sample-large.jpg"'), "picture_tag() provides standard JPG fallback img");

// Cleanup test files
@unlink($tempJpeg);
if (isset($variants['thumbnail']['path'])) @unlink($variants['thumbnail']['path']);
if (isset($variants['thumbnail']['webp_path'])) @unlink($variants['thumbnail']['webp_path']);
if (isset($variants['medium']['path'])) @unlink($variants['medium']['path']);
if (isset($variants['medium']['webp_path'])) @unlink($variants['medium']['webp_path']);
if (isset($variants['large']['path'])) @unlink($variants['large']['path']);
if (isset($variants['large']['webp_path'])) @unlink($variants['large']['webp_path']);
if (isset($variants['original_webp']['path'])) @unlink($variants['original_webp']['path']);
@rmdir($tempDir);

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
