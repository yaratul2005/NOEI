<?php

declare(strict_types=1);

/**
 * Public Web Route Registrations for NOEI CMS.
 */

use App\Controllers\PublicController;
use App\Controllers\SeoController;
use Core\Router;

/** @var Router $router */

// SEO & Crawlers
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$router->get('/robots.txt', [SeoController::class, 'robots']);

// Public Content Views
$router->get('/', [PublicController::class, 'index']);
$router->get('/post/{slug}', [PublicController::class, 'showPost']);
$router->get('/page/{slug}', [PublicController::class, 'showPage']);
$router->get('/category/{slug}', [PublicController::class, 'showCategory']);
$router->get('/tag/{slug}', [PublicController::class, 'showTag']);
