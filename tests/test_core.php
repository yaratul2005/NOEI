<?php

declare(strict_types=1);

/**
 * CLI Test Suite for NOEI CMS Core Foundation.
 */

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use Core\Autoloader;
use Core\Database;
use Core\Event;
use Core\Request;
use Core\Response;
use Core\Router;
use Core\View;

$failed = 0;
$passed = 0;

function assertTest(bool $condition, string $description): void
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

echo "=== NOEI CMS Core Foundation Verification Suite ===\n\n";

// 1. Autoloader Verification
echo "[1] Testing Autoloader...\n";
assertTest(class_exists(\Core\Request::class), "Autoloader dynamically loads Core\\Request");
assertTest(class_exists(\Core\Response::class), "Autoloader dynamically loads Core\\Response");
assertTest(class_exists(\Core\Router::class), "Autoloader dynamically loads Core\\Router");

// 2. Request Verification
echo "\n[2] Testing Request...\n";
$req = new Request(
    get: ['page' => '2', 'search' => 'bangla'],
    post: ['title' => 'Hello World', '_method' => 'PUT'],
    server: [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/posts/10?page=2',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        'REMOTE_ADDR' => '192.168.1.50'
    ]
);
assertTest($req->getMethod() === 'PUT', "Request respects _method override (PUT)");
assertTest($req->getPath() === '/posts/10', "Request strips query params from path (/posts/10)");
assertTest($req->get('page') === '2', "Request fetches GET parameters");
assertTest($req->post('title') === 'Hello World', "Request fetches POST parameters");
assertTest($req->isAjax() === true, "Request identifies AJAX headers");
assertTest($req->ip() === '192.168.1.50', "Request retrieves IP address");

// 3. Response Verification
echo "\n[3] Testing Response...\n";
$res = new Response("Test Content", 201, ['X-Custom-Header' => 'NOEI']);
assertTest($res->getStatusCode() === 201, "Response status code set to 201");
assertTest($res->getHeader('X-Custom-Header') === 'NOEI', "Response header correctly stored");

$jsonRes = new Response();
$jsonRes->json(['status' => 'success', 'code' => 200]);
assertTest(str_contains($jsonRes->getContent(), '"status":"success"'), "Response json helper formats output correctly");
assertTest($jsonRes->getHeader('Content-Type') === 'application/json; charset=utf-8', "Response json helper sets JSON content-type");

// 4. Router Verification
echo "\n[4] Testing Router...\n";
$router = new Router();
$middlewareExecuted = false;

$router->group('/admin', function (Router $r) use (&$middlewareExecuted) {
    $r->get('/users/{id:\d+}', function (Request $req, array $params) {
        return new Response("User ID: " . $params['id']);
    });
}, [
    function (Request $req, callable $next, array $params) use (&$middlewareExecuted) {
        $middlewareExecuted = true;
        return $next($req);
    }
]);

$testReq = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/users/42']);
$testRes = $router->dispatch($testReq);
assertTest($middlewareExecuted === true, "Router executes group middleware pipeline");
assertTest($testRes->getContent() === 'User ID: 42', "Router extracts regex path params ({id}) and dispatches handler");

// 404 test
$notFoundReq = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/unknown/route']);
$notFoundRes = $router->dispatch($notFoundReq);
assertTest($notFoundRes->getStatusCode() === 404, "Router responds with 404 for unmatched routes");

// 5. View Engine & Escaping Verification
echo "\n[5] Testing View Engine...\n";
$escaped = e("<script>alert('xss');</script>");
assertTest($escaped === "&lt;script&gt;alert(&#039;xss&#039;);&lt;/script&gt;", "e() auto-escapes HTML special characters");

// 6. Event Hook Verification
echo "\n[6] Testing Event Hooks...\n";
$actionRan = false;
Event::addAction('user_login', function ($user) use (&$actionRan) {
    $actionRan = $user;
});
Event::doAction('user_login', 'AdminUser');
assertTest($actionRan === 'AdminUser', "Event::doAction triggers registered action listeners");

Event::addFilter('content_title', function ($title) {
    return "Filtered: " . $title;
});
$filteredTitle = Event::applyFilters('content_title', 'My Post');
assertTest($filteredTitle === "Filtered: My Post", "Event::applyFilters transforms content via filter pipeline");

// 7. Database PDO Verification (SQLite In-Memory Mock)
echo "\n[7] Testing Database PDO Wrapper...\n";
$sqlitePdo = new PDO('sqlite::memory:');
$sqlitePdo->exec("CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT)");
Database::setPdo($sqlitePdo);
$db = Database::getInstance();

$db->execute("INSERT INTO test_table (name) VALUES (:name)", ['name' => 'Bangla CMS']);
$insertedRow = $db->fetch("SELECT * FROM test_table WHERE name = :name", ['name' => 'Bangla CMS']);
assertTest($insertedRow !== false && $insertedRow['name'] === 'Bangla CMS', "Database PDO wrapper executes prepared statements safely");

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
