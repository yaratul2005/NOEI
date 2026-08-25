<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\FlashService;
use App\Services\OptionService;
use Core\Request;
use Core\Response;
use Core\View;

/**
 * Visual Theme Customizer Controller for NOEI CMS.
 */
class CustomizerController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    /**
     * Display Visual Customizer screen.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $html = View::render('admin/customizer/index', [
            'title' => 'Theme Customizer - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'customizer',
            'options' => [
                'theme_logo' => OptionService::get('theme_logo', ''),
                'theme_favicon' => OptionService::get('theme_favicon', ''),
                'theme_primary_color' => OptionService::get('theme_primary_color', '#2563eb'),
                'theme_accent_color' => OptionService::get('theme_accent_color', '#1d4ed8'),
                'custom_css' => OptionService::get('custom_css', ''),
                'custom_head_scripts' => OptionService::get('custom_head_scripts', ''),
                'custom_footer_scripts' => OptionService::get('custom_footer_scripts', ''),
            ],
        ]);

        return new Response($html);
    }

    /**
     * Save theme customizer settings.
     *
     * @param Request $request
     * @return Response
     */
    public function save(Request $request): Response
    {
        $fields = [
            'theme_logo',
            'theme_favicon',
            'theme_primary_color',
            'theme_accent_color',
            'custom_css',
            'custom_head_scripts',
            'custom_footer_scripts',
        ];

        foreach ($fields as $field) {
            $val = $request->post($field, '');
            OptionService::set($field, (string)$val, true);
        }

        FlashService::success('Theme customization settings saved successfully.');

        $response = new Response();
        return $response->redirect(base_url('/admin/customizer'));
    }
}
