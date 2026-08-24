<?php

declare(strict_types=1);

namespace App\Services;

use Core\Event;
use RuntimeException;
use ZipArchive;

/**
 * Permission-Governed Module Engine & Extension Lifecycle Manager for NOEI CMS.
 */
class ModuleService
{
    private string $modulesDir;
    private static array $instantiatedModules = [];

    public function __construct(?string $modulesDir = null)
    {
        $this->modulesDir = rtrim($modulesDir ?? dirname(__DIR__, 2) . '/modules', '/\\');
    }

    /**
     * Get all discovered modules in the /modules/ directory.
     *
     * @return array<string, array>
     */
    public function getDiscoveredModules(): array
    {
        $modules = [];
        if (!is_dir($this->modulesDir)) {
            mkdir($this->modulesDir, 0755, true);
            return $modules;
        }

        $activeSlugs = $this->getActiveSlugs();
        $dirs = glob("{$this->modulesDir}/*", GLOB_ONLYDIR) ?: [];

        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $manifestFile = "{$dir}/module.json";

            if (file_exists($manifestFile)) {
                $content = file_get_contents($manifestFile);
                $manifest = json_decode((string)$content ?: '{}', true);

                if (is_array($manifest) && !empty($manifest['name'])) {
                    $modules[$slug] = array_merge([
                        'slug' => $slug,
                        'name' => ucfirst($slug),
                        'version' => '1.0.0',
                        'description' => '',
                        'author' => 'Unknown',
                        'author_uri' => '',
                        'permissions' => [],
                        'main' => "{$slug}.php",
                        'min_php_version' => '8.1.0',
                        'min_cms_version' => '1.0.0',
                        'path' => str_replace('\\', '/', $dir),
                        'is_active' => in_array($slug, $activeSlugs, true),
                    ], $manifest);
                }
            }
        }

        return $modules;
    }

    /**
     * Get list of currently active module slugs.
     *
     * @return array<string>
     */
    public function getActiveSlugs(): array
    {
        $json = OptionService::get('active_modules', '[]');
        if (is_array($json)) {
            return $json;
        }
        return json_decode((string)$json ?: '[]', true) ?: [];
    }

    /**
     * Save active module slugs to options.
     *
     * @param array<string> $slugs
     */
    public function setActiveSlugs(array $slugs): void
    {
        $slugs = array_values(array_unique(array_filter($slugs)));
        OptionService::set('active_modules', json_encode($slugs, JSON_UNESCAPED_UNICODE), true);
    }

    /**
     * Instantiate and boot all active modules with error isolation.
     */
    public function bootActiveModules(): void
    {
        $activeSlugs = $this->getActiveSlugs();
        $discovered = $this->getDiscoveredModules();

        foreach ($activeSlugs as $slug) {
            if (!isset($discovered[$slug])) {
                continue;
            }

            $moduleInfo = $discovered[$slug];
            $mainPath = "{$moduleInfo['path']}/{$moduleInfo['main']}";

            if (!file_exists($mainPath)) {
                continue;
            }

            try {
                require_once $mainPath;

                // Find class name from file or convention
                $className = pathinfo($moduleInfo['main'], PATHINFO_FILENAME);
                if (!class_exists($className)) {
                    // Try PSR namespace if defined
                    $namespacedClass = "Modules\\" . str_replace('-', '', ucwords($slug, '-')) . "\\{$className}";
                    if (class_exists($namespacedClass)) {
                        $className = $namespacedClass;
                    }
                }

                if (class_exists($className)) {
                    $instance = new $className();
                    self::$instantiatedModules[$slug] = $instance;

                    if (method_exists($instance, 'boot')) {
                        $instance->boot();
                    }
                }
            } catch (\Throwable $e) {
                // Log error without crashing core application
                error_log("NOEI Module Boot Error [{$slug}]: " . $e->getMessage());
            }
        }
    }

    /**
     * Activate a module.
     *
     * @param string $slug
     * @return bool
     */
    public function activate(string $slug): bool
    {
        $discovered = $this->getDiscoveredModules();
        if (!isset($discovered[$slug])) {
            return false;
        }

        $moduleInfo = $discovered[$slug];
        $mainPath = "{$moduleInfo['path']}/{$moduleInfo['main']}";

        if (file_exists($mainPath)) {
            try {
                require_once $mainPath;
                $className = pathinfo($moduleInfo['main'], PATHINFO_FILENAME);
                if (class_exists($className)) {
                    $instance = new $className();
                    if (method_exists($instance, 'onActivate')) {
                        $instance->onActivate();
                    }
                }
            } catch (\Throwable $e) {
                error_log("NOEI Module Activation Error [{$slug}]: " . $e->getMessage());
                return false;
            }
        }

        $activeSlugs = $this->getActiveSlugs();
        if (!in_array($slug, $activeSlugs, true)) {
            $activeSlugs[] = $slug;
            $this->setActiveSlugs($activeSlugs);
        }

        return true;
    }

    /**
     * Deactivate a module.
     *
     * @param string $slug
     * @return bool
     */
    public function deactivate(string $slug): bool
    {
        $discovered = $this->getDiscoveredModules();
        if (isset($discovered[$slug])) {
            $moduleInfo = $discovered[$slug];
            $mainPath = "{$moduleInfo['path']}/{$moduleInfo['main']}";

            if (file_exists($mainPath)) {
                try {
                    require_once $mainPath;
                    $className = pathinfo($moduleInfo['main'], PATHINFO_FILENAME);
                    if (class_exists($className)) {
                        $instance = new $className();
                        if (method_exists($instance, 'onDeactivate')) {
                            $instance->onDeactivate();
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("NOEI Module Deactivation Error [{$slug}]: " . $e->getMessage());
                }
            }
        }

        $activeSlugs = $this->getActiveSlugs();
        $activeSlugs = array_values(array_filter($activeSlugs, fn($s) => $s !== $slug));
        $this->setActiveSlugs($activeSlugs);

        return true;
    }

    /**
     * Uninstall a module (trigger onUninstall and delete folder).
     *
     * @param string $slug
     * @return bool
     */
    public function uninstall(string $slug): bool
    {
        $this->deactivate($slug);

        $discovered = $this->getDiscoveredModules();
        if (!isset($discovered[$slug])) {
            return false;
        }

        $moduleInfo = $discovered[$slug];
        $mainPath = "{$moduleInfo['path']}/{$moduleInfo['main']}";

        if (file_exists($mainPath)) {
            try {
                require_once $mainPath;
                $className = pathinfo($moduleInfo['main'], PATHINFO_FILENAME);
                if (class_exists($className)) {
                    $instance = new $className();
                    if (method_exists($instance, 'onUninstall')) {
                        $instance->onUninstall();
                    }
                }
            } catch (\Throwable $e) {
                error_log("NOEI Module Uninstall Error [{$slug}]: " . $e->getMessage());
            }
        }

        // Delete module directory
        $this->deleteDirectory($moduleInfo['path']);
        return true;
    }

    /**
     * Install module from an uploaded ZIP archive.
     *
     * @param array $file Structure from $_FILES['module_zip']
     * @return string Installed module slug
     */
    public function installZip(array $file): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("ZipArchive extension is required for module zip installation.");
        }

        $tmpFile = $file['tmp_name'] ?? '';
        if (empty($tmpFile) || !file_exists($tmpFile)) {
            throw new RuntimeException("No valid zip file uploaded.");
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            throw new RuntimeException("Could not open module zip archive.");
        }

        // Locate module.json inside archive
        $manifestIndex = -1;
        $manifestPath = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = $stat['name'] ?? '';
            if (basename($filename) === 'module.json') {
                $manifestIndex = $i;
                $manifestPath = $filename;
                break;
            }
        }

        if ($manifestIndex === -1) {
            $zip->close();
            throw new RuntimeException("Invalid module package: missing module.json manifest.");
        }

        $manifestJson = $zip->getFromIndex($manifestIndex);
        $manifest = json_decode((string)$manifestJson ?: '{}', true);

        if (!is_array($manifest) || empty($manifest['slug'])) {
            $zip->close();
            throw new RuntimeException("Invalid module.json: missing required 'slug' attribute.");
        }

        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($manifest['slug']));
        $targetDir = "{$this->modulesDir}/{$slug}";

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $zip->extractTo($targetDir);
        $zip->close();

        return $slug;
    }

    /**
     * Recursively delete directory.
     *
     * @param string $dir
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
