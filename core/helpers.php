<?php

declare(strict_types=1);

/**
 * Global Helper Functions for NOEI CMS.
 */

use App\Services\OptionService;
use App\Services\SeoService;
use Core\Database;
use Core\I18n;
use Core\PostType;
use Core\Shortcode;

$GLOBALS['_cms_post_meta_cache'] = [];

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

if (!function_exists('__')) {
    /**
     * Translate string key using the active I18n catalog.
     *
     * @param string $key
     * @param array<string, string|int|float> $replace
     * @return string
     */
    function __(string $key, array $replace = []): string
    {
        return I18n::translate($key, $replace);
    }
}

if (!function_exists('_e')) {
    /**
     * Translate and echo escaped string.
     *
     * @param string $key
     * @param array<string, string|int|float> $replace
     */
    function _e(string $key, array $replace = []): void
    {
        echo htmlspecialchars(I18n::translate($key, $replace), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('app_locale')) {
    /**
     * Get active application locale (e.g. 'en', 'bn').
     *
     * @return string
     */
    function app_locale(): string
    {
        return I18n::getLocale();
    }
}

if (!function_exists('set_locale')) {
    /**
     * Set active application locale.
     *
     * @param string $locale
     */
    function set_locale(string $locale): void
    {
        I18n::setLocale($locale);
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
     * Retrieve a custom post metadata field from cms_post_meta with in-memory caching.
     *
     * @param int $postId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function get_post_meta(int $postId, string $key, mixed $default = null): mixed
    {
        global $_cms_post_meta_cache;
        if (isset($_cms_post_meta_cache[$postId]) && array_key_exists($key, $_cms_post_meta_cache[$postId])) {
            return $_cms_post_meta_cache[$postId][$key];
        }

        try {
            $db = Database::getInstance();
            $val = $db->fetchColumn(
                "SELECT meta_value FROM cms_post_meta WHERE post_id = :post_id AND meta_key = :meta_key LIMIT 1",
                ['post_id' => $postId, 'meta_key' => $key]
            );

            if ($val === false || $val === null) {
                $_cms_post_meta_cache[$postId][$key] = $default;
                return $default;
            }

            $decoded = json_decode((string)$val, true);
            $res = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) ? $decoded : $val;
            $_cms_post_meta_cache[$postId][$key] = $res;
            return $res;
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
        global $_cms_post_meta_cache;
        try {
            $db = Database::getInstance();
            $metaValue = is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;

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

            $_cms_post_meta_cache[$postId][$key] = $value;
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
        global $_cms_post_meta_cache;
        try {
            $db = Database::getInstance();
            $db->execute(
                "DELETE FROM cms_post_meta WHERE post_id = :post_id AND meta_key = :meta_key",
                ['post_id' => $postId, 'meta_key' => $key]
            );
            unset($_cms_post_meta_cache[$postId][$key]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('picture_tag')) {
    /**
     * Render semantic HTML5 <picture> tag with WebP source and fallback img.
     *
     * @param int|array|string $media Media ID, media record array, or image path
     * @param string $size 'thumbnail'|'medium'|'large'|'original'
     * @param string $alt
     * @param string $class
     * @return string
     */
    function picture_tag(int|array|string $media, string $size = 'large', string $alt = '', string $class = ''): string
    {
        $imgSrc = '';
        $webpSrc = '';

        if (is_int($media)) {
            try {
                $db = Database::getInstance();
                $row = $db->fetch("SELECT * FROM cms_media WHERE id = :id LIMIT 1", ['id' => $media]);
                if ($row) {
                    $media = $row;
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        if (is_array($media)) {
            $meta = [];
            if (!empty($media['meta_data'])) {
                $meta = is_array($media['meta_data']) ? $media['meta_data'] : json_decode((string)$media['meta_data'], true);
            }

            $imgSrc = $media['file_path'] ?? '';
            $alt = !empty($alt) ? $alt : ($media['filename'] ?? '');

            // Check if size variant exists
            if ($size !== 'original' && isset($meta['variants'][$size])) {
                $v = $meta['variants'][$size];
                $imgSrc = $v['path'] ?? $imgSrc;
                if (!empty($v['webp_path'])) {
                    $webpSrc = $v['webp_path'];
                }
            } elseif (isset($meta['variants']['original_webp']['path'])) {
                $webpSrc = $meta['variants']['original_webp']['path'];
            }
        } elseif (is_string($media)) {
            $imgSrc = $media;
            $pathInfo = pathinfo($media);
            $ext = strtolower($pathInfo['extension'] ?? '');
            if ($ext !== 'webp') {
                $webpSrc = ($pathInfo['dirname'] ?? '') . '/' . ($pathInfo['filename'] ?? '') . '.webp';
            }
        }

        if (empty($imgSrc)) {
            return '';
        }

        $imgUrl = base_url($imgSrc);
        $escapedAlt = htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escapedClass = !empty($class) ? ' class="' . htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';

        if (!empty($webpSrc)) {
            $webpUrl = base_url($webpSrc);
            return sprintf(
                '<picture><source srcset="%s" type="image/webp"><img src="%s" alt="%s"%s loading="lazy"></picture>',
                htmlspecialchars($webpUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars($imgUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $escapedAlt,
                $escapedClass
            );
        }

        return sprintf(
            '<img src="%s" alt="%s"%s loading="lazy">',
            htmlspecialchars($imgUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $escapedAlt,
            $escapedClass
        );
    }
}
