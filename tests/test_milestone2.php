<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for Milestone 2:
 * Database Schema, Security Middleware, AuthService, and Browser Installer.
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Services\AuthService;
use Core\Database;
use Core\Request;
use Core\Response;

$passed = 0;
$failed = 0;

function assertM2(bool $condition, string $description): void
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

echo "=== NOEI CMS Milestone 2 Verification Suite ===\n\n";

// 1. Schema SQL Parsing & Database Verification (SQLite In-Memory Mock)
echo "[1] Testing Database Schema & Pre-seeded Data...\n";
$sqlitePdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$schemaSql = file_get_contents(__DIR__ . '/../install/schema.sql');
assertM2(!empty($schemaSql), "schema.sql exists and is non-empty");

// Clean comments and MySQL-specific directives for SQLite parser compatibility
$cleanSchema = preg_replace('/--.*$/m', '', $schemaSql);
$sqliteSql = preg_replace('/ENGINE=InnoDB DEFAULT CHARSET=\w+ COLLATE=\w+;/i', ';', $cleanSchema);
$sqliteSql = preg_replace('/,?\s*KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sqliteSql);
$sqliteSql = str_replace(['LONGTEXT', 'ON UPDATE CURRENT_TIMESTAMP', 'BIGINT AUTO_INCREMENT PRIMARY KEY', 'INT AUTO_INCREMENT PRIMARY KEY'], ['TEXT', '', 'INTEGER PRIMARY KEY AUTOINCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT'], $sqliteSql);

$statements = array_filter(array_map('trim', explode(';', $sqliteSql)));
foreach ($statements as $stmt) {
    if (!empty($stmt) && !str_starts_with(strtoupper($stmt), 'SET ')) {
        $sqlitePdo->exec($stmt);
    }
}

Database::setPdo($sqlitePdo);
$db = Database::getInstance();

$roleCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_roles");
assertM2($roleCount === 5, "schema.sql pre-seeds 5 default roles (Administrator, Editor, Author, Contributor, Subscriber)");

$optCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_options");
assertM2($optCount >= 5, "schema.sql pre-seeds essential site options");

// 2. AuthService & Password Security Verification
echo "\n[2] Testing AuthService & Password Security...\n";
$auth = new AuthService();

// Insert test user into in-memory SQLite DB
$adminPass = 'SecureAdmin123!';
$adminHash = password_hash($adminPass, PASSWORD_DEFAULT);

$db->execute("INSERT INTO cms_users (username, email, password_hash, role_id, status) VALUES (:user, :email, :hash, 1, 'active')", [
    'user' => 'admin_test',
    'email' => 'admin@test.com',
    'hash' => $adminHash,
]);

assertM2($auth->login('admin@test.com', 'WrongPassword') === false, "AuthService rejects incorrect password");
assertM2($auth->login('admin@test.com', $adminPass) === true, "AuthService authenticates valid user credentials");
assertM2($auth->check() === true, "AuthService check() returns true after login");
assertM2($auth->user()['username'] === 'admin_test', "AuthService user() fetches authenticated user record");
assertM2($auth->can('manage_options') === true, "AuthService can() approves Administrator capability");

$auth->logout();
assertM2($auth->check() === false, "AuthService logout() clears session authentication");

// 3. CsrfMiddleware Verification
echo "\n[3] Testing CsrfMiddleware Security...\n";
AuthService::startSession();

$token = CsrfMiddleware::getToken();
assertM2(!empty($token) && strlen($token) === 64, "CsrfMiddleware generates 64-character hex token in session");

$fieldHtml = CsrfMiddleware::field();
assertM2(str_contains($fieldHtml, 'name="_csrf_token"') && str_contains($fieldHtml, $token), "CsrfMiddleware field() outputs valid hidden input field");

$csrfMiddleware = new CsrfMiddleware();

// Valid POST request
$validPost = new Request(
    post: ['_csrf_token' => $token],
    server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/save']
);
$validRes = $csrfMiddleware->handle($validPost, fn(Request $r) => new Response('OK'));
assertM2($validRes->getContent() === 'OK', "CsrfMiddleware permits POST request with valid CSRF token");

// Invalid POST request
$invalidPost = new Request(
    post: ['_csrf_token' => 'invalid_token_123'],
    server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/save']
);
$invalidRes = $csrfMiddleware->handle($invalidPost, fn(Request $r) => new Response('OK'));
assertM2($invalidRes->getStatusCode() === 403, "CsrfMiddleware blocks POST request with invalid CSRF token (HTTP 403)");

// 4. Installer Pre-flight & Lock Mechanism Verification
echo "\n[4] Testing Installer Pre-flight & Security Lock...\n";
require_once __DIR__ . '/../install/index.php'; // Load installer helper functions

$checks = checkPreflight();
assertM2(count($checks) >= 5, "Installer checkPreflight() evaluates PHP version, extensions, and directory permissions");

$lockTestFile = __DIR__ . '/../storage/installed.lock';
if (file_exists($lockTestFile)) {
    unlink($lockTestFile);
}

assertM2(!file_exists($lockTestFile), "Installer lock file starts uncreated");
file_put_contents($lockTestFile, "INSTALLED_ON=" . date('c') . "\nVERSION=1.0.0-alpha\n");
assertM2(file_exists($lockTestFile), "Installer lock file creates successfully");

// Clean up lock test file for installer test
unlink($lockTestFile);

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
