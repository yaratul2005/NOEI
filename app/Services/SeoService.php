<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;

/**
 * SEO, Canonical, Open Graph, and Twitter Cards Metadata Generation Service for NOEI CMS.
 */
class SeoService
{
    /**
     * Render dynamic SEO <head> HTML tags for current page/post context.
     *
     * @param array $context Current view context (e.g., ['post' => [...]] or ['page' => [...]] or ['title' => '...'])
     * @return string
     */
    public static function renderHead(array $context = []): string
    {
        $siteTitle = (string)OptionService::get('site_title', 'NOEI CMS');
        $tagline = (string)OptionService::get('site_tagline', 'A Modern, Fast & Clean CMS');
        $siteUrl = rtrim((string)OptionService::get('site_url', 'http://localhost'), '/');
        $defaultDescription = (string)OptionService::get('seo_description', 'A modern, fast, secure, and shared-hosting-first PHP/MySQL Content Management System.');
        $fallbackImage = (string)OptionService::get('seo_fallback_image', "{$siteUrl}/public/assets/images/NOEI.svg");
        $googleVerification = (string)OptionService::get('google_site_verification', '');

        $title = $siteTitle;
        $description = $defaultDescription;
        $canonicalUrl = $siteUrl . ($_SERVER['REQUEST_URI'] ?? '/');
        $ogType = 'website';
        $ogImage = $fallbackImage;
        $noindex = false;

        $post = $context['post'] ?? null;
        $page = $context['page'] ?? null;
        $entity = $post ?? $page;

        if ($entity && is_array($entity)) {
            $title = (!empty($entity['title']) ? $entity['title'] . " - " : "") . $siteTitle;
            $excerpt = !empty($entity['excerpt']) ? $entity['excerpt'] : substr(strip_tags($entity['content'] ?? ''), 0, 160);
            if (!empty($excerpt)) {
                $description = trim((string)$excerpt);
            }

            if (!empty($entity['slug'])) {
                $prefix = isset($context['post']) ? '/post/' : '/page/';
                $canonicalUrl = "{$siteUrl}{$prefix}{$entity['slug']}";
            }

            $ogType = isset($context['post']) ? 'article' : 'website';

            // Check per-post custom meta overrides if database exists
            if (!empty($entity['id'])) {
                try {
                    $db = Database::getInstance();
                    $metaRows = $db->fetchAll("SELECT meta_key, meta_value FROM cms_post_meta WHERE post_id = :id", ['id' => (int)$entity['id']]);
                    $postMeta = [];
                    foreach ($metaRows as $row) {
                        $postMeta[$row['meta_key']] = $row['meta_value'];
                    }

                    if (!empty($postMeta['seo_title'])) {
                        $title = $postMeta['seo_title'];
                    }
                    if (!empty($postMeta['seo_description'])) {
                        $description = $postMeta['seo_description'];
                    }
                    if (!empty($postMeta['noindex']) && $postMeta['noindex'] === '1') {
                        $noindex = true;
                    }
                } catch (\Throwable $e) {
                    // Ignore DB errors in fallback scenarios
                }
            }
        } elseif (!empty($context['archiveTitle'])) {
            $title = "{$context['archiveTitle']} - {$siteTitle}";
            $description = "Browse posts filed under {$context['archiveTitle']} on {$siteTitle}.";
        } elseif (!empty($context['title'])) {
            $title = $context['title'];
        }

        $html = [];
        $html[] = '<title>' . e($title) . '</title>';
        $html[] = '<meta name="description" content="' . e($description) . '">';
        $html[] = '<link rel="canonical" href="' . e($canonicalUrl) . '">';

        if ($noindex) {
            $html[] = '<meta name="robots" content="noindex, follow">';
        }

        if (!empty($googleVerification)) {
            $html[] = '<meta name="google-site-verification" content="' . e($googleVerification) . '">';
        }

        // Open Graph
        $html[] = '<meta property="og:locale" content="en_US">';
        $html[] = '<meta property="og:type" content="' . e($ogType) . '">';
        $html[] = '<meta property="og:title" content="' . e($title) . '">';
        $html[] = '<meta property="og:description" content="' . e($description) . '">';
        $html[] = '<meta property="og:url" content="' . e($canonicalUrl) . '">';
        $html[] = '<meta property="og:site_name" content="' . e($siteTitle) . '">';
        if (!empty($ogImage)) {
            $html[] = '<meta property="og:image" content="' . e($ogImage) . '">';
        }

        // Twitter Cards
        $html[] = '<meta name="twitter:card" content="summary_large_image">';
        $html[] = '<meta name="twitter:title" content="' . e($title) . '">';
        $html[] = '<meta name="twitter:description" content="' . e($description) . '">';
        if (!empty($ogImage)) {
            $html[] = '<meta name="twitter:image" content="' . e($ogImage) . '">';
        }

        return implode("\n    ", $html);
    }
}
