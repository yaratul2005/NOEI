<?php

declare(strict_types=1);

/**
 * Global Helper Functions for NOEI CMS.
 */

use App\Services\OptionService;
use App\Services\SeoService;
use Core\Database;
use Core\PostType;
use Core\Shortcode;

if (!function_exists('e')) {
    /**
     * Safe HTML output escaping helper function.
     * Prevents Cross-Site Scripting (XSS) in views and dynamic output.
     *
     * @param string|null $value
     * @return string
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /**
     * Generate dynamic relative URL based on current subfolder or root path.
     *
     * @param string|null $path
     * @return string
     */
    function base_url(?string $path = ''): string
    {
        if ($path !== null && (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))) {
            return $path;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = str_replace('\\', '/', dirname($scriptName));
        $base = ($dir === '/' || $dir === '.') ? '' : '/' . trim($dir, '/');

        if (empty($path) || $path === '/') {
            return empty($base) ? '/' : $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate full absolute URL including scheme, host, and subfolder path.
     *
     * @param string|null $path
     * @return string
     */
    function url(?string $path = ''): string
    {
        if ($path !== null && (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))) {
            return $path;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');

        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

        $rel = base_url($path);
        return "{$scheme}://{$host}" . (str_starts_with($rel, '/') ? $rel : "/{$rel}");
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset path relative to installation base directory.
     *
     * @param string $path
     * @return string
     */
    function asset(string $path): string
    {
        return base_url($path);
    }
}

if (!function_exists('option')) {
    /**
     * Retrieve site configuration option by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function option(string $key, mixed $default = null): mixed
    {
        return OptionService::get($key, $default);
    }
}

if (!function_exists('seo_head')) {
    /**
     * Render SEO, Canonical, Open Graph, and Twitter Cards tags.
     *
     * @param array $context
     * @return string
     */
    function seo_head(array $context = []): string
    {
        return SeoService::renderHead($context);
    }
}

if (!function_exists('do_shortcode')) {
    /**
     * Parse and render all registered shortcodes within content.
     *
     * @param string $content
     * @return string
     */
    function do_shortcode(string $content): string
    {
        return Shortcode::parse($content);
    }
}

if (!function_exists('add_shortcode')) {
    /**
     * Register a new shortcode tag handler.
     *
     * @param string $tag
     * @param callable $callback
     */
    function add_shortcode(string $tag, callable $callback): void
    {
        Shortcode::add($tag, $callback);
    }
}

if (!function_exists('register_post_type')) {
    /**
     * Register a new Custom Post Type.
     *
     * @param string $type
     * @param array $args
     */
    function register_post_type(string $type, array $args = []): void
    {
        PostType::register($type, $args);
    }
}

if (!function_exists('get_post_types')) {
    /**
     * Get all registered post types.
     *
     * @return array
     */
    function get_post_types(): array
    {
        return PostType::all();
    }
}

if (!function_exists('get_post_meta')) {
    /**
     * Retrieve a custom post metadata field from cms_post_meta.
     *
     * @param int $postId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get_post_meta(int $postId, string $key, mixed $default = null): mixed
    {
        try {
            $db = Database::getInstance();
            $val = $db->fetchColumn(
                "SELECT meta_value FROM cms_post_meta WHERE post_id = :post_id AND meta_key = :meta_key LIMIT 1",
                ['post_id' => $postId, 'meta_key' => $key]
            );

            if ($val === false || $val === null) {
                return $default;
            }

            $decoded = json_decode((string)$val, true);
            return (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) ? $decoded : $val;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('update_post_meta')) {
    /**
     * Update or insert a custom post metadata field in cms_post_meta.
     *
     * @param int $postId
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    function update_post_meta(int $postId, string $key, mixed $value): bool
    {
        try {
            $db = Database::getInstance();
            $metaValue = is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
            $now = date('Y-m-d H:i:s');

            $existingId = $db->fetchColumn(
                "SELECT id FROM cms_post_meta WHERE post_id = :post_id AND meta_key = :meta_key LIMIT 1",
                ['post_id' => $postId, 'meta_key' => $key]
            );

            if ($existingId !== false && $existingId !== null) {
                $db->execute(
                    "UPDATE cms_post_meta SET meta_value = :meta_value WHERE id = :id",
                    ['meta_value' => $metaValue, 'id' => (int)$existingId]
                );
            } else {
                $db->execute(
                    "INSERT INTO cms_post_meta (post_id, meta_key, meta_value) VALUES (:post_id, :meta_key, :meta_value)",
                    ['post_id' => $postId, 'meta_key' => $key, 'meta_value' => $metaValue]
                );
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('delete_post_meta')) {
    /**
     * Delete a custom post metadata field from cms_post_meta.
     *
     * @param int $postId
     * @param string $key
     * @return bool
     */
    function delete_post_meta(int $postId, string $key): bool
    {
        try {
            $db = Database::getInstance();
            $db->execute(
                "DELETE FROM cms_post_meta WHERE post_id = :post_id AND meta_key = :meta_key",
                ['post_id' => $postId, 'meta_key' => $key]
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
