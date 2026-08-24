<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use RuntimeException;

/**
 * Theme Discovery, Template Hierarchy Resolution, and Theme Engine for NOEI CMS.
 */
class ThemeService
{
    private string $themesDir;

    public function __construct(?string $themesDir = null)
    {
        $this->themesDir = rtrim($themesDir ?? dirname(__DIR__, 2) . '/themes', '/\\');
    }

    /**
     * Get currently active theme name.
     *
     * @return string
     */
    public function getActiveTheme(): string
    {
        try {
            $db = Database::getInstance();
            $active = $db->fetchColumn("SELECT option_value FROM cms_options WHERE option_name = 'active_theme' LIMIT 1");
            return !empty($active) ? (string)$active : 'default';
        } catch (\Throwable $e) {
            return 'default';
        }
    }

    /**
     * Scan themes directory and return all valid installed themes.
     *
     * @return array<string, array>
     */
    public function getThemes(): array
    {
        $themes = [];
        if (!is_dir($this->themesDir)) {
            return $themes;
        }

        $dirs = glob("{$this->themesDir}/*", GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $manifestPath = "{$dir}/theme.json";

            if (file_exists($manifestPath)) {
                $content = file_get_contents($manifestPath);
                $manifest = json_decode($content ?: '{}', true) ?: [];

                $themes[$slug] = array_merge([
                    'name' => ucfirst($slug),
                    'slug' => $slug,
                    'version' => '1.0.0',
                    'author' => 'Unknown',
                    'locations' => ['primary', 'footer'],
                    'path' => $dir,
                ], $manifest);
            }
        }

        return $themes;
    }

    /**
     * Resolve template file path according to hierarchy rules.
     *
     * @param string $type 'home'|'single'|'page'|'archive'|'404'
     * @param array $params Context parameters (e.g. ['slug' => 'about', 'taxonomy' => 'category'])
     * @return string Absolute file path to resolved template
     */
    public function resolveTemplate(string $type, array $params = []): string
    {
        $activeTheme = $this->getActiveTheme();
        $themePath = "{$this->themesDir}/{$activeTheme}";

        $slug = $params['slug'] ?? '';
        $taxonomy = $params['taxonomy'] ?? '';

        $candidates = match ($type) {
            'home' => ['front-page.php', 'home.php', 'index.php'],
            'single' => ["single-{$slug}.php", 'single.php', 'index.php'],
            'page' => ["page-{$slug}.php", 'page.php', 'index.php'],
            'archive' => ["{$taxonomy}.php", 'archive.php', 'index.php'],
            '404' => ['404.php', 'index.php'],
            default => ['index.php'],
        };

        foreach ($candidates as $candidate) {
            $fullPath = "{$themePath}/{$candidate}";
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }

        // Fallback default theme index.php
        $fallback = "{$this->themesDir}/default/index.php";
        if (file_exists($fallback)) {
            return $fallback;
        }

        throw new RuntimeException("No valid template found for type [{$type}] in theme [{$activeTheme}].");
    }

    /**
     * Render a theme template with passed data variables.
     *
     * @param string $type
     * @param array $data
     * @return string
     */
    public function render(string $type, array $data = []): string
    {
        $params = [
            'slug' => $data['post']['slug'] ?? ($data['page']['slug'] ?? ''),
            'taxonomy' => $data['taxonomy'] ?? '',
        ];

        $templateFile = $this->resolveTemplate($type, $params);

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $templateFile;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean() ?: '';
    }
}
