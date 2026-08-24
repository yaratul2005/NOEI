<?php

declare(strict_types=1);

/**
 * Headless RESTful API Routes for NOEI CMS.
 */

use App\Controllers\Api\MediaApiController;
use App\Controllers\Api\PageApiController;
use App\Controllers\Api\PostApiController;
use App\Controllers\Api\SiteApiController;
use App\Controllers\Api\TaxonomyApiController;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\CorsMiddleware;
use Core\Router;

/** @var Router $router */

$router->group('/api/v1', function (Router $r) {
    // Public Site Info
    $r->get('/site', [SiteApiController::class, 'info']);

    // Posts Endpoints
    $r->get('/posts', [PostApiController::class, 'index']);
    $r->get('/posts/{idOrSlug}', [PostApiController::class, 'show']);
    $r->post('/posts', [PostApiController::class, 'store'], [ApiAuthMiddleware::class]);
    $r->put('/posts/{id:\d+}', [PostApiController::class, 'update'], [ApiAuthMiddleware::class]);
    $r->delete('/posts/{id:\d+}', [PostApiController::class, 'delete'], [ApiAuthMiddleware::class]);

    // Pages Endpoints
    $r->get('/pages', [PageApiController::class, 'index']);
    $r->get('/pages/{idOrSlug}', [PageApiController::class, 'show']);
    $r->post('/pages', [PageApiController::class, 'store'], [ApiAuthMiddleware::class]);
    $r->put('/pages/{id:\d+}', [PageApiController::class, 'update'], [ApiAuthMiddleware::class]);
    $r->delete('/pages/{id:\d+}', [PageApiController::class, 'delete'], [ApiAuthMiddleware::class]);

    // Taxonomies Endpoints
    $r->get('/categories', [TaxonomyApiController::class, 'categories']);
    $r->get('/tags', [TaxonomyApiController::class, 'tags']);

    // Media Endpoints
    $r->get('/media', [MediaApiController::class, 'index']);
    $r->post('/media/upload', [MediaApiController::class, 'upload'], [ApiAuthMiddleware::class]);
}, [
    CorsMiddleware::class,
]);
