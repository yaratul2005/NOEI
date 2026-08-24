<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\SeoController;
use App\Models\Post;
use App\Services\AuthService;
use App\Services\FlashService;
use App\Services\OptionService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Admin Site Settings, Configuration, and API Token Controller.
 */
class SettingController
{
    private AuthService $auth;
    private Post $postModel;

    public function __construct(?AuthService $auth = null, ?Post $postModel = null)
    {
        $this->auth = $auth ?? new AuthService();
        $this->postModel = $postModel ?? new Post();
    }

    /**
     * General settings page.
     *
     * @param Request $request
     * @return Response
     */
    public function general(Request $request): Response
    {
        $html = View::render('admin/settings/general', [
            'title' => 'General Settings - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'settings',
            'tab' => 'general',
            'options' => OptionService::all(),
        ]);

        return new Response($html);
    }

    /**
     * Reading settings page.
     *
     * @param Request $request
     * @return Response
     */
    public function reading(Request $request): Response
    {
        $pages = $this->postModel->getAll('page');

        $html = View::render('admin/settings/reading', [
            'title' => 'Reading Settings - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'settings',
            'tab' => 'reading',
            'options' => OptionService::all(),
            'pages' => $pages,
        ]);

        return new Response($html);
    }

    /**
     * SEO & Social settings page.
     *
     * @param Request $request
     * @return Response
     */
    public function seo(Request $request): Response
    {
        $html = View::render('admin/settings/seo', [
            'title' => 'SEO & Social Settings - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'settings',
            'tab' => 'seo',
            'options' => OptionService::all(),
        ]);

        return new Response($html);
    }

    /**
     * Robots.txt settings page.
     *
     * @param Request $request
     * @return Response
     */
    public function robots(Request $request): Response
    {
        $siteUrl = rtrim((string)OptionService::get('site_url', 'http://localhost'), '/');
        $defaultRobots = "User-agent: *\nDisallow: /admin/\nDisallow: /storage/\n\nSitemap: {$siteUrl}/sitemap.xml\n";

        $html = View::render('admin/settings/robots', [
            'title' => 'Robots.txt Editor - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'settings',
            'tab' => 'robots',
            'robotsTxt' => OptionService::get('robots_txt', $defaultRobots),
        ]);

        return new Response($html);
    }

    /**
     * API Key settings page.
     *
     * @param Request $request
     * @return Response
     */
    public function api(Request $request): Response
    {
        $html = View::render('admin/settings/api', [
            'title' => 'REST API Tokens - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'settings',
            'tab' => 'api',
            'options' => OptionService::all(),
        ]);

        return new Response($html);
    }

    /**
     * Generate or regenerate API Key.
     *
     * @param Request $request
     * @return Response
     */
    public function generateApiKey(Request $request): Response
    {
        $newKey = 'noei_' . bin2hex(random_bytes(24));
        OptionService::set('api_key', $newKey, true);

        FlashService::success('New API access token generated.');
        $response = new Response();
        return $response->redirect('/admin/settings/api');
    }

    /**
     * Revoke API Key.
     *
     * @param Request $request
     * @return Response
     */
    public function revokeApiKey(Request $request): Response
    {
        OptionService::delete('api_key');

        FlashService::success('API access token revoked.');
        $response = new Response();
        return $response->redirect('/admin/settings/api');
    }

    /**
     * Save settings for a specific tab.
     *
     * @param Request $request
     * @param array $params
     * @return Response
     */
    public function save(Request $request, array $params): Response
    {
        $tab = (string)($params['tab'] ?? 'general');
        $posts = $request->all();

        // Remove CSRF and system params
        unset($posts['csrf_token'], $posts['_method']);

        foreach ($posts as $key => $val) {
            OptionService::set((string)$key, $val, true);
        }

        // Invalidate sitemap cache whenever site options change
        SeoController::clearSitemapCache();

        FlashService::success('Settings updated successfully.');

        $response = new Response();
        return $response->redirect("/admin/settings/{$tab}");
    }
}
