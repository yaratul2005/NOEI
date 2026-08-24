<?php

declare(strict_types=1);

/**
 * Admin Route Registrations for NOEI CMS.
 */

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\BackupController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\MenuController;
use App\Controllers\Admin\ModuleController;
use App\Controllers\Admin\PageController;
use App\Controllers\Admin\PostController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\TaxonomyController;
use App\Controllers\Admin\UpdateController;
use App\Controllers\Admin\UserController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Core\Router;

/** @var Router $router */

// Public Auth Routes
$router->get('/admin/login', [AuthController::class, 'showLogin']);
$router->post('/admin/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
$router->get('/admin/logout', [AuthController::class, 'logout']);
$router->post('/admin/logout', [AuthController::class, 'logout'], [CsrfMiddleware::class]);

// API Endpoints
$router->get('/api/v1/media', [MediaController::class, 'apiList'], [AuthMiddleware::class]);

// Protected Admin Panel Routes
$router->group('/admin', function (Router $r) {
    // Dashboard & Profile
    $r->get('/dashboard', [DashboardController::class, 'index']);
    $r->get('/profile', [AuthController::class, 'showProfile']);
    $r->post('/profile', [AuthController::class, 'updateProfile']);

    // User Management (RBAC)
    $r->get('/users', [UserController::class, 'index']);
    $r->get('/users/create', [UserController::class, 'create']);
    $r->post('/users', [UserController::class, 'store']);
    $r->get('/users/{id:\d+}/edit', [UserController::class, 'edit']);
    $r->post('/users/{id:\d+}', [UserController::class, 'update']);
    $r->post('/users/{id:\d+}/delete', [UserController::class, 'delete']);

    // Post Engine Routes
    $r->get('/posts', [PostController::class, 'index']);
    $r->get('/posts/create', [PostController::class, 'create']);
    $r->post('/posts', [PostController::class, 'store']);
    $r->get('/posts/{id:\d+}/edit', [PostController::class, 'edit']);
    $r->post('/posts/{id:\d+}', [PostController::class, 'update']);
    $r->post('/posts/{id:\d+}/delete', [PostController::class, 'delete']);
    $r->get('/posts/{id:\d+}/revisions', [PostController::class, 'revisions']);
    $r->post('/posts/revisions/{revisionId:\d+}/restore', [PostController::class, 'restoreRevision']);

    // Hierarchical Page Engine Routes
    $r->get('/pages', [PageController::class, 'index']);
    $r->get('/pages/create', [PageController::class, 'create']);
    $r->post('/pages', [PageController::class, 'store']);
    $r->get('/pages/{id:\d+}/edit', [PageController::class, 'edit']);
    $r->post('/pages/{id:\d+}', [PageController::class, 'update']);
    $r->post('/pages/{id:\d+}/delete', [PageController::class, 'delete']);

    // Taxonomies (Categories & Tags)
    $r->get('/categories', [TaxonomyController::class, 'categories']);
    $r->post('/categories', [TaxonomyController::class, 'storeCategory']);
    $r->post('/categories/{id:\d+}/delete', [TaxonomyController::class, 'deleteCategory']);
    $r->get('/tags', [TaxonomyController::class, 'tags']);
    $r->post('/tags', [TaxonomyController::class, 'storeTag']);
    $r->post('/tags/{id:\d+}/delete', [TaxonomyController::class, 'deleteTag']);

    // Media Library Routes
    $r->get('/media', [MediaController::class, 'index']);
    $r->post('/media/upload', [MediaController::class, 'upload']);
    $r->post('/media/{id:\d+}', [MediaController::class, 'update']);
    $r->post('/media/{id:\d+}/delete', [MediaController::class, 'delete']);

    // Navigation Menu Builder Routes
    $r->get('/menus', [MenuController::class, 'index']);
    $r->post('/menus', [MenuController::class, 'store']);

    // Extension Modules Routes
    $r->get('/modules', [ModuleController::class, 'index']);
    $r->post('/modules/upload', [ModuleController::class, 'upload']);
    $r->post('/modules/{slug:[a-zA-Z0-9_-]+}/activate', [ModuleController::class, 'activate']);
    $r->post('/modules/{slug:[a-zA-Z0-9_-]+}/deactivate', [ModuleController::class, 'deactivate']);

    // Site Configuration & Settings Routes
    $r->get('/settings/general', [SettingController::class, 'general']);
    $r->post('/settings/general', [SettingController::class, 'save']);
    $r->get('/settings/reading', [SettingController::class, 'reading']);
    $r->post('/settings/reading', [SettingController::class, 'save']);
    $r->get('/settings/seo', [SettingController::class, 'seo']);
    $r->post('/settings/seo', [SettingController::class, 'save']);
    $r->get('/settings/robots', [SettingController::class, 'robots']);
    $r->post('/settings/robots', [SettingController::class, 'save']);
    $r->get('/settings/api', [SettingController::class, 'api']);
    $r->post('/settings/api/generate', [SettingController::class, 'generateApiKey']);
    $r->post('/settings/api/revoke', [SettingController::class, 'revokeApiKey']);

    // Backups & Restoration Routes
    $r->get('/backups', [BackupController::class, 'index']);
    $r->post('/admin/backups/create-db', [BackupController::class, 'createDb']);
    $r->post('/backups/create-db', [BackupController::class, 'createDb']);
    $r->post('/admin/backups/create-full', [BackupController::class, 'createFull']);
    $r->post('/backups/create-full', [BackupController::class, 'createFull']);
    $r->post('/backups/restore', [BackupController::class, 'restore']);
    $r->get('/backups/download/{filename:[a-zA-Z0-9_.-]+}', [BackupController::class, 'download']);
    $r->post('/backups/delete/{filename:[a-zA-Z0-9_.-]+}', [BackupController::class, 'delete']);

    // Updates & Rollback Routes
    $r->get('/updates', [UpdateController::class, 'index']);
    $r->post('/updates/check', [UpdateController::class, 'check']);
    $r->post('/updates/apply', [UpdateController::class, 'apply']);
    $r->post('/updates/rollback', [UpdateController::class, 'rollback']);
}, [
    AuthMiddleware::class,
    CsrfMiddleware::class,
]);
