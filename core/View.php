<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

/**
 * Server-Rendered HTML Template Engine for NOEI CMS.
 * Provides template loading, dynamic variable binding, and auto-escaping security helpers.
 */
class View
{
    private static string $viewsPath = '';
    private ?string $layout = null;
    private array $sections = [];
    private ?string $currentSection = null;

    /**
     * Set global views root directory.
     *
     * @param string $path
     */
    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/\\');
    }

    /**
     * Get global views root directory.
     *
     * @return string
     */
    public static function getViewsPath(): string
    {
        if (empty(self::$viewsPath)) {
            self::$viewsPath = dirname(__DIR__) . '/app/Views';
        }
        return self::$viewsPath;
    }

    /**
     * Render a view file with data array.
     *
     * @param string $view Relative view file path (e.g. 'admin/dashboard' or 'pages/show')
     * @param array<string, mixed> $data
     * @return string Rendered HTML content
     */
    public static function render(string $view, array $data = []): string
    {
        $viewInstance = new self();
        return $viewInstance->renderView($view, $data);
    }

    /**
     * Internal view rendering instance method.
     *
     * @param string $view
     * @param array<string, mixed> $data
     * @return string
     */
    public function renderView(string $view, array $data = []): string
    {
        $viewFile = self::getViewsPath() . '/' . trim($view, '/') . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View file [{$view}] not found at {$viewFile}.");
        }

        // Export safe escaping function and data variables into template scope
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $viewFile;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $content = ob_get_clean() ?: '';

        if ($this->layout !== null) {
            $layoutFile = self::getViewsPath() . '/' . trim($this->layout, '/') . '.php';
            if (!file_exists($layoutFile)) {
                throw new RuntimeException("Layout file [{$this->layout}] not found at {$layoutFile}.");
            }

            $data['content'] = $content;
            extract($data, EXTR_SKIP);

            ob_start();
            try {
                require $layoutFile;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return ob_get_clean() ?: '';
        }

        return $content;
    }

    /**
     * Define parent layout file for the current view.
     *
     * @param string $layout
     */
    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Start a layout section block.
     *
     * @param string $name
     */
    public function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End a layout section block.
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException("Cannot end section without starting one.");
        }
        $this->sections[$this->currentSection] = ob_get_clean() ?: '';
        $this->currentSection = null;
    }

    /**
     * Render a layout section.
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }
}
