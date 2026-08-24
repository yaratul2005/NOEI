<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\MenuService;
use App\Services\OptionService;
use Core\Request;
use Core\Response;

/**
 * Headless RESTful API Controller for Public Site Info and Navigation Menus.
 */
class SiteApiController
{
    private MenuService $menuService;

    public function __construct(?MenuService $menuService = null)
    {
        $this->menuService = $menuService ?? new MenuService();
    }

    /**
     * Get site information and public navigation menus.
     *
     * @param Request $request
     * @return Response
     */
    public function info(Request $request): Response
    {
        $siteInfo = [
            'name' => OptionService::get('site_title', 'NOEI CMS'),
            'tagline' => OptionService::get('site_tagline', ''),
            'url' => OptionService::get('site_url', 'http://localhost'),
            'theme' => OptionService::get('theme', 'default'),
            'date_format' => OptionService::get('date_format', 'F j, Y'),
            'menus' => [
                'primary' => $this->menuService->getMenuByLocation('primary')['items'] ?? [],
                'footer' => $this->menuService->getMenuByLocation('footer')['items'] ?? [],
            ],
        ];

        $response = new Response();
        return $response->json([
            'success' => true,
            'data' => $siteInfo,
        ]);
    }
}
