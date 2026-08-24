<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuthService;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Dashboard Controller.
 * Aggregates system metrics, post counts, user counts, and environment stats.
 */
class DashboardController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    /**
     * Display Dashboard Overview.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $db = Database::getInstance();

        // Stats aggregation
        $postCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_posts WHERE type = 'post'");
        $pageCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_posts WHERE type = 'page'");
        $userCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_users");
        $mediaCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_media");

        // Environment information
        $serverInfo = [
            'php_version' => PHP_VERSION,
            'pdo_driver' => $db->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME),
            'max_upload' => ini_get('upload_max_filesize'),
            'memory_limit' => ini_get('memory_limit'),
        ];

        $html = View::render('admin/dashboard/index', [
            'title' => 'Dashboard - NOEI CMS',
            'user' => $user,
            'currentRoute' => 'dashboard',
            'stats' => [
                'posts' => $postCount,
                'pages' => $pageCount,
                'users' => $userCount,
                'media' => $mediaCount,
            ],
            'server' => $serverInfo,
        ]);

        return new Response($html);
    }
}
