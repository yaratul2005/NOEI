<?php

declare(strict_types=1);

/**
 * Automated Verification Suite for Milestone 3:
 * Admin Dashboard Shell, Authentication System, and User Management (RBAC).
 */

define('NOEI_TESTING', true);

require_once __DIR__ . '/../core/Autoloader.php';
\Core\Autoloader::register();

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\UserController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\AuthService;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\Router;

$passed = 0;
$failed = 0;

function assertM3(bool $condition, string $description): void
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

echo "=== NOEI CMS Milestone 3 Verification Suite ===\n\n";

// 1. Initialize SQLite In-Memory Database & Seed Base Data
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
$db = Database::getInstance();

// Create Initial Superadmin User (ID: 1)
$superadminPass = 'SuperAdmin123!';
$superadminHash = password_hash($superadminPass, PASSWORD_DEFAULT);
$db->execute("INSERT INTO cms_users (username, email, password_hash, role_id, status) VALUES ('superadmin', 'admin@noei.com', :hash, 1, 'active')", [
    'hash' => $superadminHash,
]);

assertM3((int)$db->fetchColumn("SELECT COUNT(*) FROM cms_users") === 1, "Database initialized with primary Superadmin account");

// 2. Authentication Controller & Security Tests
echo "\n[2] Testing Authentication Controller & Route Guard...\n";
$auth = new AuthService();
$authController = new AuthController($auth);

// Test 2.1: Unauthorized Access Redirect
$authMiddleware = new AuthMiddleware($auth);
$unauthReq = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/dashboard']);
$unauthRes = $authMiddleware->handle($unauthReq, fn() => new Response('OK'));
assertM3($unauthRes->getStatusCode() === 302 && $unauthRes->getHeader('Location') === '/admin/login', "AuthMiddleware redirects unauthenticated requests to /admin/login");

// Test 2.2: Login Execution
$loginReq = new Request(
    post: ['login' => 'superadmin', 'password' => $superadminPass],
    server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/login']
);
$loginRes = $authController->login($loginReq);
assertM3($auth->check() === true, "AuthController authenticates valid user credentials");
assertM3($loginRes->getHeader('Location') === '/admin/dashboard', "AuthController redirects to /admin/dashboard on login success");

// 3. Dashboard Controller Tests
echo "\n[3] Testing Dashboard Controller Metrics...\n";
$dashboardController = new DashboardController($auth);
$dashReq = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/dashboard']);
$dashRes = $dashboardController->index($dashReq);
assertM3(str_contains($dashRes->getContent(), 'Dashboard Overview'), "DashboardController renders overview page HTML");
assertM3(str_contains($dashRes->getContent(), 'Total Users'), "DashboardController displays aggregated metric cards");

// 4. User Profile Update Tests
echo "\n[4] Testing User Profile Management...\n";
$profileUpdateReq = new Request(
    post: ['email' => 'updatedadmin@noei.com', 'password' => 'NewPassword123!'],
    server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/profile']
);
$profileRes = $authController->updateProfile($profileUpdateReq);
$updatedUser = $auth->user();
assertM3($updatedUser['email'] === 'updatedadmin@noei.com', "AuthController updates email address");
assertM3(password_verify('NewPassword123!', $updatedUser['password_hash']), "AuthController updates password hash");

// 5. User Management & RBAC CRUD Tests
echo "\n[5] Testing User Management (RBAC CRUD)...\n";
$userController = new UserController($auth);

// Test 5.1: Create New User
$createUserReq = new Request(
    post: [
        'username' => 'editor_user',
        'email' => 'editor@noei.com',
        'password' => 'EditorPass123!',
        'role_id' => 2, // Editor
        'status' => 'active'
    ],
    server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/users']
);
$userController->store($createUserReq);
$editorId = (int)$db->fetchColumn("SELECT id FROM cms_users WHERE username = 'editor_user'");
assertM3($editorId > 0, "UserController store() creates new Editor user account");

// Test 5.2: Edit User Details
$editUserReq = new Request(
    post: [
        'email' => 'editor_new@noei.com',
        'role_id' => 2,
        'status' => 'active',
        'password' => ''
    ],
    server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/admin/users/{$editorId}"]
);
$userController->update($editUserReq, ['id' => (string)$editorId]);
$editedUser = $db->fetch("SELECT * FROM cms_users WHERE id = :id", ['id' => $editorId]);
assertM3($editedUser['email'] === 'editor_new@noei.com', "UserController update() modifies existing user details");

// 6. User Management Safeguards
echo "\n[6] Testing User Management Safeguards...\n";

// Safeguard 1: Self-deletion Block
$selfDeleteReq = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/users/1/delete']);
$userController->delete($selfDeleteReq, ['id' => '1']);
$adminExists = (bool)$db->fetchColumn("SELECT COUNT(*) FROM cms_users WHERE id = 1");
assertM3($adminExists === true, "UserController delete() blocks logged-in user from deleting their own account");

// Safeguard 2: Last Administrator Protection
// Create second admin to test deletion then last-admin block
$db->execute("INSERT INTO cms_users (username, email, password_hash, role_id, status) VALUES ('admin2', 'admin2@noei.com', 'hash', 1, 'active')");
$admin2Id = (int)$db->fetchColumn("SELECT id FROM cms_users WHERE username = 'admin2'");

// Login as editor_user to delete admin2
$auth->login('editor_new@noei.com', 'EditorPass123!');
// Give editor temporarily manage_users to test last-admin block
$db->execute("INSERT INTO cms_role_permission (role_id, permission_id) VALUES (2, 2)");

// Delete admin2 (Allowed because Superadmin ID 1 still exists)
$deleteAdmin2Req = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/admin/users/{$admin2Id}/delete"]);
$userController->delete($deleteAdmin2Req, ['id' => (string)$admin2Id]);
$admin2Deleted = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_users WHERE id = :id", ['id' => $admin2Id]) === 0;
assertM3($admin2Deleted === true, "UserController permits deleting Administrator if other Administrators exist");

// Attempt to delete remaining Superadmin ID 1 as editor_user
$deleteLastAdminReq = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/users/1/delete']);
$userController->delete($deleteLastAdminReq, ['id' => '1']);
$lastAdminPreserved = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_users WHERE id = 1") === 1;
assertM3($lastAdminPreserved === true, "UserController delete() prevents removing the sole remaining Administrator account");

echo "\n=== Summary: {$passed} Passed, {$failed} Failed ===\n";
exit($failed > 0 ? 1 : 0);
