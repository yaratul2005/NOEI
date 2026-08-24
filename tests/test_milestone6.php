<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for Milestone 6:
 * Theme System, Template Hierarchy, Visual Menu Builder, and Public Frontend Controller.
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Controllers\PublicController;
use App\Models\Post;
use App\Services\MenuService;
use App\Services\ThemeService;
use Core\Database;
use Core\Request;

$passed = 0;
$failed = 0;

function assertM6(bool $condition, string $description): void
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

echo "=== NOEI CMS Milestone 6 Verification Suite ===\n\n";

// 1. Initialize In-Memory Database
echo "[1] Setting up In-Memory Database for Frontend & Themes...\n";
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

// 2. Testing Theme Engine & Template Hierarchy
echo "\n[2] Testing Theme Engine & Template Hierarchy Resolution...\n";
$themeService = new ThemeService();

$themes = $themeService->getThemes();
assertM6(isset($themes['default']), "ThemeService discovers 'default' theme in themes directory");

$homeTpl = $themeService->resolveTemplate('home');
assertM6(str_ends_with(str_replace('\\', '/', $homeTpl), 'themes/default/index.php'), "ThemeService resolves homepage to index.php template");

$singleTpl = $themeService->resolveTemplate('single');
assertM6(str_ends_with(str_replace('\\', '/', $singleTpl), 'themes/default/single.php'), "ThemeService resolves single post to single.php template");

$pageTpl = $themeService->resolveTemplate('page');
assertM6(str_ends_with(str_replace('\\', '/', $pageTpl), 'themes/default/page.php'), "ThemeService resolves single page to page.php template");

$tpl404 = $themeService->resolveTemplate('404');
assertM6(str_ends_with(str_replace('\\', '/', $tpl404), 'themes/default/404.php'), "ThemeService resolves 404 to 404.php template");

// 3. Testing Visual Menu Builder & HTML Rendering
echo "\n[3] Testing Visual Menu Builder & Semantic HTML Rendering...\n";
$menuService = new MenuService();

$menuService->saveMenu('primary', [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'About Us', 'url' => '/page/about-us'],
]);

$navHtml = $menuService->render('primary', 'site-nav', '/page/about-us');
assertM6(str_contains($navHtml, '<nav class="site-nav">') && str_contains($navHtml, 'About Us'), "MenuService renders semantic <nav> HTML structure");
assertM6(str_contains($navHtml, '<li class="active"><a href="/page/about-us">About Us</a></li>'), "MenuService highlights active URI item with active class");

// 4. Testing Public Frontend Controller Responses
echo "\n[4] Testing Public Frontend Controller Responses...\n";
$postModel = new Post($db);

// Create published post & draft post
$pubPostId = $postModel->create([
    'author_id' => 1,
    'title' => 'Published Public Article',
    'slug' => 'published-public-article',
    'content' => 'Full public article content.',
    'type' => 'post',
    'status' => 'published',
]);

$draftPostId = $postModel->create([
    'author_id' => 1,
    'title' => 'Secret Draft Article',
    'slug' => 'secret-draft-article',
    'content' => 'Unpublished content.',
    'type' => 'post',
    'status' => 'draft',
]);

$pubController = new PublicController($postModel, null, $themeService);

// Test 4.1: Homepage HTTP 200
$reqHome = new Request([], [], [], [], ['REQUEST_URI' => '/']);
$resHome = $pubController->index($reqHome);
assertM6($resHome->getStatusCode() === 200, "PublicController index() returns HTTP 200 for homepage");
assertM6(str_contains($resHome->getContent(), 'Published Public Article'), "Homepage feed displays published articles");

// Test 4.2: Published Post HTTP 200
$reqPub = new Request([], [], [], [], ['REQUEST_URI' => '/post/published-public-article']);
$resPub = $pubController->showPost($reqPub, ['slug' => 'published-public-article']);
assertM6($resPub->getStatusCode() === 200, "PublicController showPost() returns HTTP 200 for published post");

// Test 4.3: Draft Post HTTP 404
$reqDraft = new Request([], [], [], [], ['REQUEST_URI' => '/post/secret-draft-article']);
$resDraft = $pubController->showPost($reqDraft, ['slug' => 'secret-draft-article']);
assertM6($resDraft->getStatusCode() === 404, "PublicController showPost() returns HTTP 404 for draft post");

// Test 4.4: Missing Post HTTP 404
$req404 = new Request([], [], [], [], ['REQUEST_URI' => '/post/non-existent']);
$res404 = $pubController->showPost($req404, ['slug' => 'non-existent']);
assertM6($res404->getStatusCode() === 404, "PublicController returns HTTP 404 for non-existent content");

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
