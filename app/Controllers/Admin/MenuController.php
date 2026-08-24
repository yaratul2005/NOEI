<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Post;
use App\Models\Taxonomy;
use App\Services\AuthService;
use App\Services\FlashService;
use App\Services\MenuService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Visual Navigation Menu Builder Controller.
 */
class MenuController
{
    private AuthService $auth;
    private MenuService $menuService;
    private Post $postModel;
    private Taxonomy $taxonomyModel;

    public function __construct(
        ?AuthService $auth = null,
        ?MenuService $menuService = null,
        ?Post $postModel = null,
        ?Taxonomy $taxonomyModel = null
    ) {
        $this->auth = $auth ?? new AuthService();
        $this->menuService = $menuService ?? new MenuService();
        $this->postModel = $postModel ?? new Post();
        $this->taxonomyModel = $taxonomyModel ?? new Taxonomy();
    }

    /**
     * Show visual menu builder interface.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $location = (string)($request->get('location') ?? 'primary');
        $menu = $this->menuService->getMenuByLocation($location);
        $pages = $this->postModel->getAll('page');
        $categories = $this->taxonomyModel->getCategories();

        $html = View::render('admin/menus/index', [
            'title' => 'Menu Builder - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'menus',
            'location' => $location,
            'menu' => $menu,
            'pages' => $pages,
            'categories' => $categories,
        ]);

        return new Response($html);
    }

    /**
     * Save menu structure.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request): Response
    {
        $location = (string)$request->post('location', 'primary');
        $labels = (array)($request->post('labels') ?? []);
        $urls = (array)($request->post('urls') ?? []);

        $items = [];
        for ($i = 0; $i < count($labels); $i++) {
            $label = trim((string)($labels[$i] ?? ''));
            $url = trim((string)($urls[$i] ?? ''));
            if (!empty($label) && !empty($url)) {
                $items[] = ['label' => $label, 'url' => $url];
            }
        }

        $this->menuService->saveMenu($location, $items);

        FlashService::success("Navigation menu for location [{$location}] saved successfully.");
        $response = new Response();
        return $response->redirect("/admin/menus?location={$location}");
    }
}
