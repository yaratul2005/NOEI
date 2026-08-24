<?php

declare(strict_types=1);

/**
 * NOEI CMS - Public Front Controller Entry Point.
 */

define('NOEI_START_TIME', microtime(true));
define('NOEI_ROOT_DIR', __DIR__);

// Load PSR-4 Fallback Autoloader
require_once NOEI_ROOT_DIR . '/core/Autoloader.php';
\Core\Autoloader::register();

use App\Services\ModuleService;
use Core\Request;
use Core\Response;
use Core\Router;
use Core\View;

$request = new Request();
$response = new Response();
$router = new Router();

// Configure View Engine root directory
View::setViewsPath(NOEI_ROOT_DIR . '/app/Views');

// Database Installation Check
$dbConfig = require NOEI_ROOT_DIR . '/config/database.php';
$isInstalled = (bool)($dbConfig['installed'] ?? false);

if (!$isInstalled && !str_starts_with($request->getPath(), '/install')) {
    if (file_exists(NOEI_ROOT_DIR . '/install/index.php')) {
        $response->redirect('/install/index.php')->send();
        exit;
    }
}

// Boot Active Extension Modules
if ($isInstalled) {
    $moduleService = new ModuleService();
    $moduleService->bootActiveModules();
}

// Health Check API Route
$router->get('/api/v1/health', function (Request $req) {
    $response = new Response();
    return $response->json([
        'status' => 'online',
        'cms' => 'NOEI CMS',
        'version' => '1.0.0-alpha',
        'php' => PHP_VERSION,
        'uptime_ms' => round((microtime(true) - NOEI_START_TIME) * 1000, 2),
    ]);
});

// Load Web, Admin & API Routes
require_once NOEI_ROOT_DIR . '/routes/web.php';
require_once NOEI_ROOT_DIR . '/routes/admin.php';
require_once NOEI_ROOT_DIR . '/routes/api.php';

// Dispatch request through router pipeline
$dispatchResponse = $router->dispatch($request);
$dispatchResponse->send();
