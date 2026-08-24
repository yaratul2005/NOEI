<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\FlashService;
use App\Services\ModuleService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Module Manager & Extension Upload Controller.
 */
class ModuleController
{
    private AuthService $auth;
    private ModuleService $moduleService;

    public function __construct(?AuthService $auth = null, ?ModuleService $moduleService = null)
    {
        $this->auth = $auth ?? new AuthService();
        $this->moduleService = $moduleService ?? new ModuleService();
    }

    /**
     * Display list of all discovered modules.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $modules = $this->moduleService->getDiscoveredModules();

        $html = View::render('admin/modules/index', [
            'title' => 'Manage Extension Modules - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'modules',
            'modules' => $modules,
        ]);

        return new Response($html);
    }

    /**
     * Activate a module.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function activate(Request $request, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');

        if ($this->moduleService->activate($slug)) {
            FlashService::success("Module [{$slug}] activated successfully.");
        } else {
            FlashService::error("Failed to activate module [{$slug}].");
        }

        $response = new Response();
        return $response->redirect('/admin/modules');
    }

    /**
     * Deactivate a module.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function deactivate(Request $request, array $params): Response
    {
        $slug = (string)($params['slug'] ?? '');

        if ($this->moduleService->deactivate($slug)) {
            FlashService::success("Module [{$slug}] deactivated successfully.");
        } else {
            FlashService::error("Failed to deactivate module [{$slug}].");
        }

        $response = new Response();
        return $response->redirect('/admin/modules');
    }

    /**
     * Upload and install a module ZIP package.
     *
     * @param Request $request
     * @return Response
     */
    public function upload(Request $request): Response
    {
        $file = $request->file('module_zip');

        if (!$file) {
            FlashService::error('Please select a module ZIP package to upload.');
            $response = new Response();
            return $response->redirect('/admin/modules');
        }

        try {
            $slug = $this->moduleService->installZip($file);
            FlashService::success("Module [{$slug}] installed successfully.");
        } catch (\Throwable $e) {
            FlashService::error('Installation failed: ' . $e->getMessage());
        }

        $response = new Response();
        return $response->redirect('/admin/modules');
    }
}
