<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\OptionService;
use Core\Database;
use Core\Request;
use Core\Response;

/**
 * Dynamic XML Sitemap and Robots.txt Controller for NOEI CMS.
 */
class SeoController
{
    private static string $cachePath = '';

    public function __construct()
    {
        if (empty(self::$cachePath)) {
            self::$cachePath = dirname(__DIR__, 2) . '/storage/cache/sitemap.xml';
        }
    }

    /**
     * Generate or serve cached XML Sitemap.
     *
     * @param Request $request
     * @return Response
     */
    public function sitemap(Request $request): Response
    {
        $cacheFile = self::$cachePath;
        $cacheDir = dirname($cacheFile);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Return cached XML if fresh (less than 1 hour old)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
            $xml = file_get_contents($cacheFile);
            $response = new Response($xml ?: '');
            $response->setHeader('Content-Type', 'application/xml; charset=utf-8');
            return $response;
        }

        $siteUrl = rtrim((string)OptionService::get('site_url', 'http://localhost'), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // 1. Homepage
        $xml .= "  <url>\n";
        $xml .= "    <loc>" . htmlspecialchars($siteUrl . '/', ENT_XML1, 'UTF-8') . "</loc>\n";
        $xml .= "    <lastmod>" . date('Y-m-d\TH:i:sP') . "</lastmod>\n";
        $xml .= "    <changefreq>daily</changefreq>\n";
        $xml .= "    <priority>1.0</priority>\n";
        $xml .= "  </url>\n";

        try {
            $db = Database::getInstance();

            // 2. Published Posts & Pages
            $posts = $db->fetchAll("SELECT slug, type, updated_at FROM cms_posts WHERE status = 'published' AND type IN ('post', 'page') ORDER BY id DESC");
            foreach ($posts as $p) {
                $prefix = ($p['type'] === 'post') ? '/post/' : '/page/';
                $url = "{$siteUrl}{$prefix}{$p['slug']}";
                $lastmod = !empty($p['updated_at']) ? date('Y-m-d\TH:i:sP', strtotime($p['updated_at'])) : date('Y-m-d\TH:i:sP');
                $priority = ($p['type'] === 'page') ? '0.8' : '0.7';

                $xml .= "  <url>\n";
                $xml .= "    <loc>" . htmlspecialchars($url, ENT_XML1, 'UTF-8') . "</loc>\n";
                $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "    <priority>{$priority}</priority>\n";
                $xml .= "  </url>\n";
            }

            // 3. Taxonomies (Categories & Tags)
            $taxonomies = $db->fetchAll(
                "SELECT t.slug, tax.taxonomy 
                 FROM cms_terms t 
                 JOIN cms_taxonomies tax ON t.id = tax.term_id 
                 ORDER BY t.id DESC"
            );
            foreach ($taxonomies as $tax) {
                $prefix = ($tax['taxonomy'] === 'category') ? '/category/' : '/tag/';
                $url = "{$siteUrl}{$prefix}{$tax['slug']}";

                $xml .= "  <url>\n";
                $xml .= "    <loc>" . htmlspecialchars($url, ENT_XML1, 'UTF-8') . "</loc>\n";
                $xml .= "    <changefreq>weekly</changefreq>\n";
                $xml .= "    <priority>0.5</priority>\n";
                $xml .= "  </url>\n";
            }
        } catch (\Throwable $e) {
            // Gracefully continue with basic URLs if DB query fails
        }

        $xml .= '</urlset>';

        @file_put_contents($cacheFile, $xml);

        $response = new Response($xml);
        $response->setHeader('Content-Type', 'application/xml; charset=utf-8');
        return $response;
    }

    /**
     * Serve dynamic robots.txt directives.
     *
     * @param Request $request
     * @return Response
     */
    public function robots(Request $request): Response
    {
        $siteUrl = rtrim((string)OptionService::get('site_url', 'http://localhost'), '/');
        $customRobots = OptionService::get('robots_txt');

        if (!empty($customRobots)) {
            $content = (string)$customRobots;
        } else {
            $content = "User-agent: *\nDisallow: /admin/\nDisallow: /storage/\n\nSitemap: {$siteUrl}/sitemap.xml\n";
        }

        $response = new Response($content);
        $response->setHeader('Content-Type', 'text/plain; charset=utf-8');
        return $response;
    }

    /**
     * Clear cached sitemap file.
     */
    public static function clearSitemapCache(): void
    {
        $cacheFile = self::$cachePath ?: (dirname(__DIR__, 2) . '/storage/cache/sitemap.xml');
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}
