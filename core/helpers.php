<?php

declare(strict_types=1);

/**
 * Global Helper Functions for NOEI CMS.
 */

use App\Services\OptionService;
use App\Services\SeoService;

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
