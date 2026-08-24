<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for Milestone 10:
 * Core REST API, Headless Endpoints, CORS Preflight, and Bearer/Token Authentication.
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Controllers\Api\PageApiController;
use App\Controllers\Api\PostApiController;
use App\Controllers\Api\SiteApiController;
use App\Controllers\Api\TaxonomyApiController;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Models\Post;
use App\Services\OptionService;
use Core\Database;
use Core\Request;
use Core\Response;

$passed = 0;
$failed = 0;

function assertM10(bool $condition, string $description): void
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

echo "=== NOEI CMS Milestone 10 Verification Suite ===\n\n";

// 1. Initialize In-Memory Database
echo "[1] Setting up In-Memory Database for REST API...\n";
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

// Seed post records
$postModel = new Post();
$publishedId = $postModel->create([
    'author_id' => 1,
    'title' => 'Headless API Published Post',
    'slug' => 'headless-api-published-post',
    'content' => 'Content for headless REST API test.',
    'type' => 'post',
    'status' => 'published',
]);

$draftId = $postModel->create([
    'author_id' => 1,
    'title' => 'Headless API Draft Post',
    'slug' => 'headless-api-draft-post',
    'content' => 'Secret draft content.',
    'type' => 'post',
    'status' => 'draft',
]);

// Set master API key
$testApiKey = 'test_secret_token_12345';
OptionService::set('api_key', $testApiKey);

// 2. Testing CORS Middleware & Preflight Handling
echo "\n[2] Testing CORS Middleware & OPTIONS Preflight Handling...\n";
$corsMiddleware = new CorsMiddleware();

$optionsRequest = new Request([], [], ['REQUEST_METHOD' => 'OPTIONS', 'REQUEST_URI' => '/api/v1/posts']);
$corsResponse = $corsMiddleware->handle($optionsRequest, fn() => new Response(''));

assertM10($corsResponse->getStatusCode() === 204, "CORS Middleware intercepts OPTIONS preflight with HTTP 204 No Content");
assertM10($corsResponse->getHeader('Access-Control-Allow-Origin') === '*', "CORS response emits Access-Control-Allow-Origin: *");
assertM10(str_contains($corsResponse->getHeader('Access-Control-Allow-Methods') ?? '', 'POST'), "CORS response lists allowable HTTP methods");

// 3. Testing Public Headless Posts API
echo "\n[3] Testing Public Headless Posts API (GET /api/v1/posts)...\n";
$postApi = new PostApiController();

$indexReq = new Request(['page' => 1, 'per_page' => 10], [], ['REQUEST_METHOD' => 'GET']);
$indexRes = $postApi->index($indexReq);
$indexData = json_decode($indexRes->getContent(), true);

assertM10($indexRes->getStatusCode() === 200, "GET /api/v1/posts returns HTTP 200");
assertM10($indexData['success'] === true && isset($indexData['pagination']), "Response includes standardized success and pagination envelope");
assertM10(count($indexData['data']) >= 1 && $indexData['data'][0]['slug'] === 'headless-api-published-post', "Public posts query only returns published articles");

// 4. Testing Single Post Details & Status Guards
echo "\n[4] Testing Single Post Details & Draft Status Guards...\n";
$showPubReq = new Request([], [], ['REQUEST_METHOD' => 'GET']);
$showPubRes = $postApi->show($showPubReq, ['idOrSlug' => 'headless-api-published-post']);
$showPubData = json_decode($showPubRes->getContent(), true);

assertM10($showPubRes->getStatusCode() === 200, "GET /api/v1/posts/{slug} returns HTTP 200 for published post");
assertM10($showPubData['data']['title'] === 'Headless API Published Post', "Single post payload contains accurate title and body");

$showDraftRes = $postApi->show($showPubReq, ['idOrSlug' => 'headless-api-draft-post']);
assertM10($showDraftRes->getStatusCode() === 404, "GET /api/v1/posts/{draftSlug} returns HTTP 404 for unauthenticated draft post");

// 5. Testing API Authentication Middleware & Protected CRUD Operations
echo "\n[5] Testing API Authentication Guard & Protected Endpoints...\n";
$apiAuth = new ApiAuthMiddleware();

// Unauthenticated POST request attempt
$unauthReq = new Request([], ['title' => 'New API Post'], ['REQUEST_METHOD' => 'POST']);
$unauthRes = $apiAuth->handle($unauthReq, fn($r) => $postApi->store($r));

assertM10($unauthRes->getStatusCode() === 401, "Protected POST /api/v1/posts rejects unauthenticated request with HTTP 401");

// Authenticated POST request with Bearer token
$authReq = new Request([], ['title' => 'Authenticated API Post', 'content' => 'Created via token.'], [
    'REQUEST_METHOD' => 'POST',
    'HTTP_AUTHORIZATION' => "Bearer {$testApiKey}",
]);

$authRes = $apiAuth->handle($authReq, fn($r) => $postApi->store($r));
$authData = json_decode($authRes->getContent(), true);

assertM10($authRes->getStatusCode() === 201, "Protected POST /api/v1/posts creates new post with HTTP 201 when Bearer token is valid");
assertM10($authData['success'] === true && $authData['data']['slug'] === 'authenticated-api-post', "Created post payload returned with generated slug");

// 6. Testing Site & Taxonomy Public Endpoints
echo "\n[6] Testing Site Info & Taxonomy Endpoints...\n";
$siteApi = new SiteApiController();
$siteRes = $siteApi->info(new Request([], [], ['REQUEST_METHOD' => 'GET']));
$siteData = json_decode($siteRes->getContent(), true);

assertM10($siteRes->getStatusCode() === 200 && isset($siteData['data']['menus']), "GET /api/v1/site returns site title and navigation menus");

$taxApi = new TaxonomyApiController();
$catRes = $taxApi->categories(new Request([], [], ['REQUEST_METHOD' => 'GET']));
$catData = json_decode($catRes->getContent(), true);

assertM10($catRes->getStatusCode() === 200 && $catData['success'] === true, "GET /api/v1/categories returns categories list");

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
