<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * Visual Navigation Menu Builder and Semantic HTML Renderer Service for NOEI CMS.
 */
class MenuService
{
    /**
     * Get all saved navigation menus.
     *
     * @return array<string, array>
     */
    public function getMenus(): array
    {
        try {
            $db = Database::getInstance();
            $json = $db->fetchColumn("SELECT option_value FROM cms_options WHERE option_name = 'nav_menus' LIMIT 1");
            return json_decode((string)$json ?: '{}', true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get menu by theme location (e.g. 'primary' or 'footer').
     *
     * @param string $location
     * @return array|null
     */
    public function getMenuByLocation(string $location): ?array
    {
        $menus = $this->getMenus();
        return $menus[$location] ?? null;
    }

    /**
     * Save menu items for a specific theme location.
     *
     * @param string $location
     * @param array $items Array of menu items: [['label' => 'Home', 'url' => '/'], ...]
     */
    public function saveMenu(string $location, array $items): void
    {
        $menus = $this->getMenus();
        $menus[$location] = [
            'location' => $location,
            'items' => $items,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $db = Database::getInstance();
        $json = json_encode($menus, JSON_UNESCAPED_UNICODE);

        $exists = (int)$db->fetchColumn("SELECT COUNT(*) FROM cms_options WHERE option_name = 'nav_menus'");
        if ($exists > 0) {
            $db->execute("UPDATE cms_options SET option_value = :val WHERE option_name = 'nav_menus'", ['val' => $json]);
        } else {
            $db->execute("INSERT INTO cms_options (option_name, option_value, autoload) VALUES ('nav_menus', :val, 1)", ['val' => $json]);
        }
    }

    /**
     * Render semantic HTML menu structure.
     *
     * @param string $location
     * @param string $navClass
     * @param string|null $currentPath Current URI path for active class matching
     * @return string HTML <nav> menu string
     */
    public function render(string $location = 'primary', string $navClass = 'site-nav', ?string $currentPath = null): string
    {
        $menu = $this->getMenuByLocation($location);
        $items = $menu['items'] ?? [];

        if (empty($items)) {
            // Fallback default menu items if no menu saved
            $items = [
                ['label' => 'Home', 'url' => '/'],
                ['label' => 'Sample Page', 'url' => '/page/sample-page'],
            ];
        }

        $currentPath = '/' . trim($currentPath ?? ($_SERVER['REQUEST_URI'] ?? '/'), '/');

        $html = '<nav class="' . e($navClass) . '"><ul>';

        foreach ($items as $item) {
            $label = $item['label'] ?? 'Link';
            $url = $item['url'] ?? '#';
            $itemPath = '/' . trim(parse_url($url, PHP_URL_PATH) ?? '/', '/');

            $isActive = ($itemPath === $currentPath);
            $activeClass = $isActive ? ' class="active"' : '';

            $children = $item['children'] ?? [];

            if (!empty($children)) {
                $html .= '<li class="has-dropdown' . ($isActive ? ' active' : '') . '">';
                $html .= '<a href="' . e($url) . '">' . e($label) . '</a>';
                $html .= '<ul class="dropdown">';
                foreach ($children as $child) {
                    $childUrl = $child['url'] ?? '#';
                    $childLabel = $child['label'] ?? 'Child Link';
                    $childPath = '/' . trim(parse_url($childUrl, PHP_URL_PATH) ?? '/', '/');
                    $childActive = ($childPath === $currentPath) ? ' class="active"' : '';

                    $html .= '<li' . $childActive . '><a href="' . e($childUrl) . '">' . e($childLabel) . '</a></li>';
                }
                $html .= '</ul>';
                $html .= '</li>';
            } else {
                $html .= '<li' . $activeClass . '><a href="' . e($url) . '">' . e($label) . '</a></li>';
            }
        }

        $html .= '</ul></nav>';

        return $html;
    }
}
